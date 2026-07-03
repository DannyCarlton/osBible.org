#!C:/xampp/python/python.exe
"""
This script demonstrates a simple search for verses containing a single keyword.
"""

import pymysql
import re

# Print CGI header
print("Content-Type: text/html\n")

print("<h1>Find verses containing a key word.</h1>")

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
    print(f"<p>Connection error: {e}</p>")
    exit()


def get_books(cursor):
    """Fetch book names into a dictionary keyed by book id."""
    query_text = "SELECT * FROM `kjv_books`"
    cursor.execute(query_text)
    results = cursor.fetchall()
    book_name = {}
    for row in results:
        book_name[row['id']] = row['book']
    return book_name


# We will start with a single word
keyword = 'love'

# Get book names
book_name = get_books(cursor)

try:
    # Use REGEXP with word boundaries to match whole words only
    # MySQL's [[:<:]] and [[:>:]] are word boundary markers
    query_text = """
        SELECT * FROM `kjvs`
        WHERE `text` REGEXP %s
        LIMIT 10
    """
    regexp_pattern = f'[[:<:]]{keyword}[[:>:]]'
    cursor.execute(query_text, (regexp_pattern,))
    results = cursor.fetchall()

    verses = {}
    if results:
        for row in results:
            vid = row['id']
            bid = row['book']
            book = book_name[bid]
            chapter = row['chapter']
            verse = row['verse']
            ref = f"{book} {chapter}:{verse}"
            # Filter out Strong's numbers
            text = re.sub(r'\{(.*?)\}', '', row['text'])
            verses[vid] = {'ref': ref, 'text': text}

    # Output the results
    print(f'<div style="width:400px;margin-left:50px;margin-bottom:20px">'
          f'<h3>keyword: &ldquo;{keyword}&rdquo;</h3></div>')

    for verse in verses.values():
        ref = verse['ref']
        text = verse['text']
        # Highlight the keyword in bold dark red
        text = re.sub(
            rf'\b{keyword}\b',
            r'<b style="color:#990000">\g<0></b>',
            text,
            flags=re.IGNORECASE
        )
        print(f'<div style="width:400px;margin-left:50px;margin-bottom:20px">'
              f'&ldquo;{text}&rdquo;&mdash;{ref}</div>')

except Exception as e:
    print(f"<p>Error: {e}</p>")
finally:
    cursor.close()
    conn.close()
