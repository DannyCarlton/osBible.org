#!C:/xampp/python/python.exe
"""
This script demonstrates the initial database connection (assuming MySQL)
and loading of a single verse.
"""

import pymysql
import re

print("Content-Type: text/html; charset=utf-8\n")

try:
    # Connect to the database
    # For demonstration purposes using db_user and db_password
    # You'll want to use something more secure
    # The database is named 'osbible'
    
    conn = pymysql.connect(
        host='localhost',
        user='db_user',
        password='db_password',
        database='osbible',
        charset='utf8mb4',
        cursorclass=pymysql.cursors.DictCursor
    )
    
    cursor = conn.cursor()
    
    # Get the list of book names from the database and put them into dictionaries
    # Books will store all book data by id
    # Bids will map book names to their ids
    
    query_text = "SELECT * FROM `kjv_books`"
    cursor.execute(query_text)
    
    Books = {}
    Bids = {}
    
    for row in cursor.fetchall():
        book_id = row['id']
        book_name = row['book']
        Books[book_id] = row
        Bids[book_name] = book_id
    
    # Get a single verse based on a simple reference
    # We start with a simple reference that won't need correcting,
    # but will need the book name converted to the id of the book
    
    reference = 'John 3:16'
    
    # Split the reference
    book, ref = reference.split(' ', 1)
    
    # Get the book id using the Bids dictionary
    bid = Bids[book]
    
    # Split the remaining ref into chapter and verse
    chapter, verse = ref.split(':')
    
    # Query for the verse
    # We limit it to one so we don't have to run through the entire table
    # after we've found the data we need
    
    query_text = """
        SELECT `text` 
        FROM `kjvs`
        WHERE `book` = %s
        AND `chapter` = %s
        AND `verse` = %s
        LIMIT 1
    """
    
    cursor.execute(query_text, (bid, chapter, verse))
    
    # Get the verse
    verse_data = cursor.fetchone()
    
    if verse_data:
        text = verse_data['text']
        
        # Filter out the Strong's numbers (we don't need them now)
        text = re.sub(r'\{(.*?)\}', '', text)
        
        # Output the verse
        print(f"&ldquo;{text}&rdquo;&mdash;{book} {chapter}:{verse}")
    else:
        print("Verse not found.")
    
    # Close the connection
    cursor.close()
    conn.close()

except Exception as e:
    print(f"<p>Error: {str(e)}</p>")