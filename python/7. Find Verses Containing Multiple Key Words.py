#!C:/xampp/python/python.exe
"""
This script demonstrates a more complicated search using multiple keywords.
Supports multiple words, quoted phrases, and wildcard (*) searches.
"""

import pymysql
import re

# Print CGI header
print("Content-Type: text/html\n")

print("<h1>Find verses containing multiple key words.</h1>")

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

# Test keywords with notes
Keywords = []
Note = []
Keywords.append('with love')
Note.append('Two keywords.')
Keywords.append('"loved her"')
Note.append('A two word phrase.')
Keywords.append('David "loved her"')
Note.append('A word and a phrase.')
Keywords.append('love*')
Note.append('Keyword with wild card.')

for k, keyword in enumerate(Keywords):
    _k = k + 1
    _keyword = keyword
    search_key_parts = []
    new_keywords = []
    verses = {}

    if '"' in keyword:                                      # Catch quotes that designate a phrase
        matches = re.findall(r'"(.*?)"', keyword)           # Pull any words within quotes into matches
        new_keywords = matches                              # Now we have a list with the phrases

        for kw in new_keywords:
            _kw = kw.replace(' ', '_')                      # Replace space with underline to preserve phrase
            keyword = keyword.replace(kw, _kw)              # e.g. 'David "loved her"' becomes 'David "loved_her"'
        keyword = keyword.replace('"', '')                   # Remove the quotes

    if ' ' in keyword:                                       # If we have multiple words...
        words = keyword.split(' ')
        for kw in words:
            kw = kw.replace('_', ' ')                        # Return any underscores to spaces
            kw = kw.replace('*', '[a-zA-Z]*')                # Change asterisks to usable regular expression
            search_key_parts.append(f"`text` REGEXP '[[:<:]]{kw}[[:>:]]'")
        search_key = ' AND '.join(search_key_parts)          # Link them with AND operators
    else:
        keyword = keyword.replace('_', ' ')                  # Phrases turned back into correct form
        keyword = keyword.replace('*', '[a-zA-Z]*')          # Turn asterisk into regular expression
        search_key = f"`text` REGEXP '[[:<:]]{keyword}[[:>:]]'"

    # Because of the Strong's coding in 'kvvs' it's not usable for a complicated search
    query_text = f"""SELECT * FROM `kjv`
                     WHERE {search_key}
                     LIMIT 8"""

    try:
        cursor.execute(query_text)
        results = cursor.fetchall()

        if results:
            for row in results:
                vid = row['id']
                book = row['book']
                chapter = row['chapter']
                verse = row['verse']
                ref = f"{book} {chapter}:{verse}"
                # Filter out Strong's numbers
                text = re.sub(r'\{(.*?)\}', '', row['text'])
                verses[vid] = {'ref': ref, 'text': text}

    except Exception as e:
        print(f"<p>Error: {e}</p><hr><p>{query_text}</p>")

    # Output: start the floating box
    print(f'<div style="float:left;width:380px;margin-left:30px;padding:15px;'
          f'margin-bottom:10px;border:1px solid #000">')

    # Reminder of the keyword
    print(f'<div style="width:380px;margin-bottom:20px;">'
          f'<h3>Example #{_k}. {Note[k]}'
          f'<small style="font-weight:normal"><br>keyword(s): {_keyword}</small>'
          f'</h3></div>')

    # Output each verse with highlighting
    for verse_data in verses.values():
        ref = verse_data['ref']
        text = verse_data['text']

        if '"' in _keyword or '*' in _keyword:
            # Rebuild keyword with underscores for phrase handling
            highlight_keyword = _keyword
            if new_keywords:
                for kw in new_keywords:
                    _kw = kw.replace(' ', '_')
                    highlight_keyword = highlight_keyword.replace(kw, _kw)
            highlight_keyword = highlight_keyword.replace('"', '')
            words = highlight_keyword.split(' ')
            for word in words:
                word = word.replace('_', ' ')
                word_pattern = word.replace('*', '[a-zA-Z]*')
                text = re.sub(
                    rf'\b{word_pattern}\b',
                    r'<b style="color:#990000">\g<0></b>',
                    text,
                    flags=re.IGNORECASE
                )
        else:
            parts = _keyword.split(' ')
            for part in parts:
                text = re.sub(
                    rf'\b{part}\b',
                    r'<b style="color:#990000">\g<0></b>',
                    text,
                    flags=re.IGNORECASE
                )

        print(f'<div style="width:380px;margin-bottom:20px">'
              f'&ldquo;{text}&rdquo;&mdash;{ref}</div>')

    print('</div>')

cursor.close()
conn.close()
