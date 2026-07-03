#!C:/xampp/python/python.exe
"""
This script demonstrates the initial database connection (assuming MySQL)
and loading of several verses.
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
    
    #############################
    # Get a selection of verses based on a simple reference
    #############################
    
    # Two typical passage references
    # The first is the standard two verse, comma delimited reference
    # The second is the dash delimited verse list
    reference1 = 'John 3:16,17'
    reference2 = 'John 3:1-16'
    
    # Split the references
    book1, ref1 = reference1.split(' ', 1)
    book2, ref2 = reference2.split(' ', 1)
    
    # Get the book ids using the Bids dictionary
    bid1 = Bids[book1]
    bid2 = Bids[book2]
    
    # Split into chapter and verses
    chapter1, verses1 = ref1.split(':')
    chapter2, verses2 = ref2.split(':')
    
    #############################
    # Process reference #1
    # Two digits separated by a comma - fetch those specific verses
    #############################
    
    first, second = verses1.split(',')
    
    query_text1 = """
        SELECT * 
        FROM `kjvs`
        WHERE `book` = %s
        AND `chapter` = %s
        AND (`verse` = %s OR `verse` = %s)
        LIMIT 2
    """
    
    cursor.execute(query_text1, (bid1, chapter1, first, second))
    
    Verses1 = {}
    
    for row in cursor.fetchall():
        verse_id = row['verse']
        text1 = row['text']
        # Filter out the Strong's numbers (we don't need them now)
        text1 = re.sub(r'\{(.*?)\}', '', text1)
        Verses1[verse_id] = text1
    
    # Build output for passage 1
    out1 = ''
    for v, text1 in Verses1.items():
        out1 += f'<p style="text-align:justify;margin:0"><b>{v}</b> {text1}</p>'
    
    print(f'<div style="width:400px;margin-left:50px"><h3>Passage #1</h3>{out1}'
          f'<p style="text-align:right">&mdash;{book1} {chapter1}:{verses1}</p></div>')
    
    #############################
    # Process reference #2
    # Beginning and ending of a list - use those as boundaries
    #############################
    
    start, end = verses2.split('-')
    
    query_text2 = """
        SELECT * 
        FROM `kjvs`
        WHERE `book` = %s
        AND `chapter` = %s
        AND `verse` >= %s
        AND `verse` <= %s
    """
    
    cursor.execute(query_text2, (bid2, chapter2, start, end))
    
    Verses2 = {}
    
    for row in cursor.fetchall():
        verse_id = row['verse']
        text2 = row['text']
        # Filter out the Strong's numbers (we don't need them now)
        text2 = re.sub(r'\{(.*?)\}', '', text2)
        Verses2[verse_id] = text2
    
    # Build output for passage 2
    out2 = ''
    for v, text2 in Verses2.items():
        out2 += f'<p style="text-align:justify;margin:0"><b>{v}</b> {text2}</p>'
    
    print(f'<div style="width:400px;margin-left:50px"><h3>Passage #2</h3>{out2}'
          f'<p style="text-align:right">&mdash;{book2} {chapter2}:{verses2}</p></div>')
    
    # Close the connection
    cursor.close()
    conn.close()

except Exception as e:
    print(f"<p>Error: {str(e)}</p>")
