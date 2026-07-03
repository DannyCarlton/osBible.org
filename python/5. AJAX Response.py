#!C:/xampp/python/python.exe
"""
This script demonstrates how to accept a keyword query and return the text 
of the verse as a JSON response (for AJAX calls).

NOTE: We will only be doing a verse reference. Using a word as the keyword 
to find verses with that word will be a future script.
"""

import pymysql
import re
import os
import json
from urllib.parse import unquote, parse_qs

# Print CGI header (JSON response)
print("Content-Type: application/json\n")

# Get keyword from query string
query_string = os.environ.get('QUERY_STRING', '')
params = parse_qs(query_string)
keyword = params.get('keyword', ['John 3:16'])[0]

corrected_ref = ''
_c = 0
chapters = 1

# Connect to the database
try:
    conn = pymysql.connect(
        host='localhost',
        user='db_user',
        password='db_password',
        database='osbible',
        cursorclass=pymysql.cursors.DictCursor
    )
    cursor = conn.cursor()
except Exception as e:
    print(json.dumps({'error': str(e)}))
    exit()


def get_verse_count(bid, chapter, cursor):
    """Get the number of verses in a given book and chapter."""
    query_text = """
        SELECT COUNT(`verse`) AS `versecount` 
        FROM `kjvs` 
        WHERE `book` = %s AND `chapter` = %s
    """
    cursor.execute(query_text, (bid, chapter))
    result = cursor.fetchone()
    return result['versecount'] if result else 0


def get_book_by_keyword(k, cursor):
    """
    Find book data by keyword, handling abbreviations, misspellings, and variations.
    Returns a dictionary with book id, name, chapters, and the numeric reference.
    """
    return_data = {
        'bid': 0,
        'book': '',
        'chapters': 0,
        'num_key': ''
    }

    # Clean the input
    _k = unquote(k)
    # Remove extra spaces
    _k = re.sub(r'(\s){2,}', ' ', _k)
    # Change periods to colons
    _k = _k.replace('.', ':')
    # Remove spaces from verse separator (;)
    _k = _k.replace('; ', ';')

    # Make ordinals regular numbers
    _k = _k.replace('1st ', '1 ')
    _k = _k.replace('2nd ', '2 ')
    _k = _k.replace('3rd ', '3 ')
    _k = _k.replace('first ', '1 ')
    _k = _k.replace('second ', '2 ')
    _k = _k.replace('third ', '3 ')
    _k = re.sub(r'^i ', '1 ', _k, flags=re.IGNORECASE)
    _k = re.sub(r'^ii ', '2 ', _k, flags=re.IGNORECASE)
    _k = re.sub(r'^iii ', '3 ', _k, flags=re.IGNORECASE)

    # Catch messed up beginning numbers (1pe or 2jn format)
    if re.match(r'^[1-3][a-zA-Z]', _k):
        _k = re.sub(r'^1', '1 ', _k)
        _k = re.sub(r'^2', '2 ', _k)
        _k = re.sub(r'^3', '3 ', _k)

    # Split by spaces
    ref_keys = _k.split(' ')

    # If first element is empty, remove it
    if ref_keys and not ref_keys[0]:
        ref_keys.pop(0)

    # If first element is a number, combine with second to make the book key
    if len(ref_keys) > 1 and re.match(r'^[1-9]', ref_keys[0]):
        book_key = ref_keys[0] + ' ' + ref_keys[1]
        num_keys = ref_keys[2] if len(ref_keys) > 2 else ''
    else:
        book_key = ref_keys[0] if ref_keys else ''
        num_keys = ref_keys[1] if len(ref_keys) > 1 else ''

    return_data['num_key'] = num_keys

    # Handle special cases
    if book_key.lower() == 'jud':
        book_key = 'Judges'
    if book_key.lower() == 'eph':
        book_key = 'Ephesians'

    # Build the query with scoring system
    query_text = """
        SELECT * FROM `kjv_books` 
        WHERE `abbr` LIKE CONCAT('%%|', %s, '|%%') 
           OR `book` LIKE %s 
           OR `kjav_abr` LIKE %s 
           OR `book` SOUNDS LIKE %s
        ORDER BY
            CASE WHEN `abbr` LIKE CONCAT('%%|', %s, '|%%') THEN 4 ELSE 0 END
          + CASE WHEN `book` LIKE %s THEN 3 ELSE 0 END
          + CASE WHEN `kjav_abr` LIKE %s THEN 2 ELSE 0 END
          + CASE WHEN `book` SOUNDS LIKE %s THEN 1 ELSE 0 END
        DESC LIMIT 1
    """

    try:
        cursor.execute(query_text, (book_key, book_key, book_key, book_key,
                                     book_key, book_key, book_key, book_key))
        result = cursor.fetchone()

        if result:
            if result['book'] == 'Psalms':
                result['book'] = 'Psalm'

            return_data['bid'] = result['id']
            return_data['book'] = result['book']
            return_data['chapters'] = result['chapters']
    except Exception as e:
        print(json.dumps({'error': f"Query error: {e}"}))

    return return_data


def put_num_ref_in_array(book_data, cursor):
    """
    Take the numeric part of the reference and return an array of chapter/verse pairs.
    Handles single chapters, verse ranges, and semicolon-separated passages.
    """
    return_data = {'BookData': book_data, 'Refs': [], 'numKeys': []}

    bid = book_data['bid']
    num_key = book_data['num_key']

    # Is it a single number?
    try:
        _num_key = int(num_key)
        is_single = (str(_num_key) == num_key)
    except (ValueError, TypeError):
        is_single = False
        _num_key = 0

    if is_single:
        # Does the book have a single chapter?
        if book_data['chapters'] == 1:
            num_key = f"1:{_num_key}"
            refs = [num_key]
        else:
            verse_count = get_verse_count(bid, num_key, cursor)
            num_key = f"{num_key}:1-{verse_count}"
            refs = [num_key]
    else:
        if ';' in str(num_key):
            num_key = num_key.rstrip(';')
            refs = num_key.split(';')
        else:
            refs = [num_key]

    return_data['Refs'] = refs

    num_keys = []
    for ref in refs:
        if ':' in str(ref):
            parts = ref.split(':')
            chapter = parts[0]
            verses = parts[1] if len(parts) > 1 else ''
            num_keys.append({'chapter': chapter, 'verses': verses})

    return_data['numKeys'] = num_keys
    return return_data


# Main processing
try:
    book_data = get_book_by_keyword(keyword, cursor)
    bid = book_data['bid']
    book = book_data['book']

    verses_data = put_num_ref_in_array(book_data, cursor)
    num_keys = verses_data['numKeys']

    out_list = []
    old_chapter = 0

    for num_key in num_keys:
        chapter = num_key['chapter']

        if old_chapter and (chapter != old_chapter):
            chapters += 1
        old_chapter = chapter

        verses = num_key['verses']
        corrected_ref += f"{book} {chapter}:{verses}; "

        # Parse verse list
        verse_list = []

        if ',' in str(verses):
            _verses = verses.split(',')
            for verse in _verses:
                if '-' in str(verse):
                    start, end = verse.split('-')
                    for i in range(int(start), int(end) + 1):
                        verse_list.append(i)
                else:
                    verse_list.append(verse)
        elif '-' in str(verses):
            start, end = verses.split('-')
            for i in range(int(start), int(end) + 1):
                verse_list.append(i)
        else:
            verse_list.append(verses)

        # Fetch each verse
        for v in verse_list:
            query_text = """
                SELECT * FROM `kjvs`
                WHERE `book` = %s
                AND `chapter` = %s
                AND `verse` = %s
                LIMIT 1
            """
            cursor.execute(query_text, (bid, chapter, v))
            verse_result = cursor.fetchone()

            if verse_result:
                text = verse_result['text']
                # Filter out Strong's numbers
                text = re.sub(r'\{(.*?)\}', '', text)

                out_list.append({
                    'book': book,
                    'chapter': chapter,
                    'verse': verse_result['verse'],
                    'text': text
                })
                _c += 1

    # Clean up corrected reference
    corrected_ref = corrected_ref.rstrip('; ')

    # Build return structure matching PHP output
    Return = {}

    if chapters == 1 and len(out_list) == 1:
        Return['ref'] = corrected_ref
        Return['text'] = {0: out_list[0]['text']}
    elif chapters == 1:
        Return['ref'] = corrected_ref
        Return['text'] = {}
        for c, verse in enumerate(out_list):
            Return['text'][c] = f"<verse><num>{verse['verse']}</num> {verse['text']}</verse>"
    else:
        Return['ref'] = corrected_ref
        Return['text'] = {}
        for c, verse in enumerate(out_list):
            Return['text'][c] = f"<verse><num>{verse['verse']}</num> {verse['text']}</verse>"

    print(json.dumps(Return))

except Exception as e:
    print(json.dumps({'error': str(e)}))
finally:
    cursor.close()
    conn.close()
