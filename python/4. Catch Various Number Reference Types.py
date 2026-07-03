#!C:/xampp/python/python.exe
"""
This script demonstrates how to catch a variety of numeric (chapter and verse) 
reference types including multiple verses, multiple chapters, lists and others.

It uses the function get_book_by_keyword which takes what is offered as the 
book (whether it's the full name, an abbreviation or a misspelling) and 
returns the data contained in the kjv_books database for that book.

We also use the function put_num_ref_in_array to take the numeric part of the 
reference and return an array of correct verses.
"""

import pymysql
import re
from urllib.parse import unquote

# Print CGI header
print("Content-Type: text/html\n")

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
    print(f"<p>Database connection error: {e}</p>")
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
        print(f"<p>Query error: {e}</p>")
    
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
            # Make it 1:n
            num_key = f"1:{_num_key}"
            refs = [num_key]
        else:
            # The ref is c:1-(end of chapter)
            verse_count = get_verse_count(bid, num_key, cursor)
            num_key = f"{num_key}:1-{verse_count}"
            refs = [num_key]
    else:
        # If semicolon is present, separate passages
        if ';' in str(num_key):
            # Catch lists that ended with a semicolon
            num_key = num_key.rstrip(';')
            refs = num_key.split(';')
        else:
            refs = [num_key]
    
    return_data['Refs'] = refs
    
    # Parse each reference into chapter and verses
    num_keys = []
    for ref in refs:
        if ':' in str(ref):
            parts = ref.split(':')
            chapter = parts[0]
            verses = parts[1] if len(parts) > 1 else ''
            num_keys.append({'chapter': chapter, 'verses': verses})
    
    return_data['numKeys'] = num_keys
    return return_data


# Test references
references = [
    '3 John 3',
    'John 3',
    'Eph 2:8,9,12',
    'Eph 2:8-10,12',
    'Luke 7:12; 8:42; 9:38',
    'Luke 7:12; 8:42; 9:38;',
    'John 3.16'
]

notes = [
    'A book with one chapter that the reference targets the verse',
    'A book with multiple chapters referencing the entire chapter',
    'Verse not in sequence',
    'List with additional verse',
    'Verses across several chapters',
    'Verses across several chapters (but ending with a stray semi-colon)',
    'Using a period instead of a colon'
]

print("<html><head><title>Catch Various Number Reference Types</title></head><body>")

# Process each reference
for n, reference in enumerate(references):
    # Initialize variables
    _c = 0
    corrected_ref = ''
    chapters = 1
    
    print("<hr>")
    
    # Reserve the original reference
    _reference = reference
    note = notes[n]
    
    # Make all book references lower case
    reference_lower = reference.lower()
    
    # Get book data
    book_data = get_book_by_keyword(reference_lower, cursor)
    
    print(f"<b>reference: {reference}</b> ({note})<br>")
    
    bid = book_data['bid']
    book = book_data['book']
    
    # Get parsed verse data
    verses_data = put_num_ref_in_array(book_data, cursor)
    num_keys = verses_data['numKeys']
    
    # Initialize output
    out_list = []
    old_chapter = 0
    
    # Process each chapter:verse reference
    for num_key in num_keys:
        chapter = num_key['chapter']
        
        # Count chapters if different from previous
        if old_chapter and (chapter != old_chapter):
            chapters += 1
        old_chapter = chapter
        
        verses = num_key['verses']
        corrected_ref += f"{book} {chapter}:{verses}; "
        
        # Parse verse list (handles commas, ranges, or single verses)
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
        
        # Fetch each verse from database
        for v in verse_list:
            query_text = """
                SELECT * FROM `kjvs`
                WHERE `book` = %s
                AND `chapter` = %s
                AND `verse` = %s
                LIMIT 1
            """
            
            try:
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
            except Exception as e:
                print(f"<p>Error retrieving verse: {e}</p>")
    
    # Clean up corrected reference
    corrected_ref = corrected_ref.rstrip('; ')
    
    # Output formatted result
    if chapters == 1 and len(out_list) == 1:
        print(f"<b>{corrected_ref}</b><br>&ldquo;{out_list[0]['text']}&rdquo;")
    elif chapters == 1:
        print(f"<b>{corrected_ref}</b><br>")
        for verse in out_list:
            print(f"<b>{verse['verse']}</b> {verse['text']}<br>")
    else:
        print(f"<b>{corrected_ref}</b><br>")
        for verse in out_list:
            print(f"<b>{verse['chapter']}:{verse['verse']}</b> {verse['text']}<br>")

print("</body></html>")

# Close database connection
cursor.close()
conn.close()
