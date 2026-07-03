#!C:/xampp/python/python.exe
"""
This script demonstrates how to catch a variety of reference types including 
abbreviations and misspellings.

It uses the function get_book_by_keyword which takes what is offered as the 
book (whether it's the full name, an abbreviation or a misspelling) and 
returns the data contained in the kjv_books database for that book.

We also use the numeric part of the reference to return verse data.
"""



#import cgi
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

def get_book_by_keyword(k, cursor):
    """
    Find book data by keyword, handling abbreviations, misspellings, and variations.
    Returns a dictionary with book id, name, chapters, and the numeric reference.
    """
    # Initialize return dictionary
    return_data = {
        'id': 0,
        'book': '',
        'chapters': 0,
        'num_key': '',
        'queryText': ''
    }
    
    # Clean the input
    _k = unquote(k)
    # Remove extra spaces
    _k = re.sub(r'(\s){2,}', ' ', _k)
    # Remove periods
    _k = _k.replace('.', '')
    
    # Make ordinals regular numbers (expecting space between ordinal and book name)
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
    
    # Explode by spaces
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
    
    # Add the chapter and verse(s) to the output
    return_data['num_key'] = num_keys
    
    # Handle special cases
    if book_key.lower() == 'jud':
        book_key = 'Judges'
    if book_key.lower() == 'eph':
        book_key = 'Ephesians'
    
    # Build the query with scoring system
    # Look in abbr column for matches (delimited by |) and score matches
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
            # Handle Psalms special case
            if result['book'] == 'Psalms':
                result['book'] = 'Psalm'
            
            return_data['id'] = result['id']
            return_data['book'] = result['book']
            return_data['chapters'] = result['chapters']
            return_data['dbRow'] = result
        
        return_data['queryText'] = cursor.mogrify(query_text, (book_key, book_key, book_key, book_key,
                                                                 book_key, book_key, book_key, book_key))
    except Exception as e:
        print(f"<p>Query error: {e}</p>")
    
    return return_data


# Test references array
references = {
    1: 'Jn 3:16',
    2: '2 pt 1:1',
    3: '2nd pt 1:1',
    4: 'ii pt 1:1',
    5: 'Second Peter 1:1',
    6: 'gnesis 1:1'
}

notes = {
    1: 'A typical abbreviation',
    2: 'Several examples of how books with numbers are entered. Just a number',
    3: '...Number with ordinal',
    4: '...Roman numeral',
    5: '...Ordinal word',
    6: 'Common misspelling'
}

print("<html><head><title>Catch Various Book Reference Types</title></head><body>")


# Process each reference
for n, reference in references.items():
    # Reserve the original reference
    _reference = reference
    
    # Get the note to display
    note = notes[n]
    
    # Make all book references lower case to avoid confusing the filter
    reference_lower = reference.lower()
    
    # Get book data using the function
    book_data = get_book_by_keyword(reference_lower, cursor)
    
    # Get the book id for the next query
    bid = book_data['id']
    book = book_data['book']
    
    # Split the chapter from the verse
    if ':' in book_data['num_key']:
        chapter, verse = book_data['num_key'].split(':')
        
        # Build the query for the verse text
        verse_query = """
            SELECT `text` FROM `kjvs`
            WHERE `book` = %s
            AND `chapter` = %s
            AND `verse` = %s
            LIMIT 1
        """
        
        try:
            cursor.execute(verse_query, (bid, chapter, verse))
            verse_result = cursor.fetchone()
            
            if verse_result:
                text = verse_result['text']
                
                # Filter out Strong's numbers (we don't need them now)
                text = re.sub(r'\{(.*?)\}', '', text)
                
                # Display the result
                print(f"Original reference: {_reference} ({note})<br>")
                print(f"Result: &ldquo;{text}&rdquo;&mdash;{book} {chapter}:{verse}<hr>")
            else:
                print(f"Original reference: {_reference} ({note})<br>")
                print(f"No verse found for {book} {chapter}:{verse}<hr>")
        except Exception as e:
            print(f"<p>Error retrieving verse: {e}</p>")
    else:
        print(f"Original reference: {_reference} ({note})<br>")
        print(f"Could not parse chapter:verse from '{book_data['num_key']}'<hr>")

print("</body></html>")

# Close database connection
cursor.close()
conn.close()