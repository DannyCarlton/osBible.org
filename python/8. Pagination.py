#!C:/xampp/python/python.exe
"""
This script demonstrates basic pagination, so a visitor can navigate through a
lengthy result set instead of receiving all of it at once.

The search itself is the same one script 7 does. What is new here is counting the
whole result, asking for only one page of it, and building the page links.
"""

import os
import re
import math
import pymysql
from urllib.parse import parse_qs

# Print CGI header
print("Content-Type: text/html\n")

print("<h1>Pagination.</h1>")

# ------------------------------------------------------------------------------
# Word boundary syntax for REGEXP.
#
# MariaDB and MySQL 5.7 and earlier use POSIX boundaries: [[:<:]]word[[:>:]]
# MySQL 8.0.4 replaced the regex engine with ICU, which uses \b instead and
# rejects the POSIX form outright with "repetition-operator operand invalid".
#
# The PHP original was written against MariaDB 10.4. This defaults to the
# MySQL 8 form. If you get a regex error, these are the two lines to change.
# ------------------------------------------------------------------------------

WB_START = '\\b'                    # MariaDB / MySQL 5.7:  '[[:<:]]'
WB_END = '\\b'                      # MariaDB / MySQL 5.7:  '[[:>:]]'

PER_PAGE = 10                       # results per page
WINDOW = 9                          # how many numbered links to show at once

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


def sql_quote(value):
    """Quote a string for inclusion in SQL.

    Normally %s placeholders handle this and a function like this is never
    written. But parse_keywords() below builds a WHERE clause of unknown shape
    -- one REGEXP condition per search term -- and a placeholder can stand in
    for a value, not for a fragment of SQL. So that fragment has to be built as
    text, and this is the one place in the set where the escaping is ours to get
    right.

    The PHP original does not escape here at all. A keyword containing a quote
    breaks the query, and a carefully chosen one could do worse. Do not copy
    this pattern for anything taking real visitor input without thinking it
    through -- prefer MySQL full-text search, or check the terms against a
    strict allowlist first.
    """
    return "'" + conn.escape_string(str(value)) + "'"


def parse_keywords(keyword):
    """Turn a search string into a SQL WHERE fragment and a list of terms to
    highlight.

    Handles three things at once:
        with love       several words, all of which must appear
        "loved her"     a quoted phrase, which must survive being split on spaces
        love*           a wildcard, which becomes [a-zA-Z]* for the REGEXP

    The trick with phrases is the PHP one: temporarily swap the space inside a
    quoted phrase for an underscore so the split does not break it apart, then
    swap it back afterwards.
    """
    working = keyword

    if '"' in working:                                  # Catch quotes that designate a phrase
        matches = re.findall(r'"(.*?)"', working)       # Pull any words within quotes
        for kw in matches:
            _kw = kw.strip().replace(' ', '_')          # 'David "loved her"' -> 'David "loved_her"'
            working = working.replace(kw, _kw)
        working = working.replace('"', '')              # Remove the quotes

    search_key_parts = []
    terms = []

    for kw in working.split(' '):                       # One word gives a one-element list
        if not kw:
            continue
        kw = kw.replace('_', ' ')                       # Return any underscores to spaces
        terms.append(kw)                                # As typed, for the highlighter
        pattern = kw.replace('*', '[a-zA-Z]*')          # Asterisk becomes a regular expression
        search_key_parts.append('`text` REGEXP ' + sql_quote(WB_START + pattern + WB_END))

    return ' AND '.join(search_key_parts), terms        # Link them with AND -- every term must be present


def highlight(text, terms):
    """Make each search term bold and dark red.

    re.escape() keeps a term containing a regex character from producing a
    broken pattern. The PHP drops the keyword straight into preg_replace and has
    that exposure. The asterisk is put back deliberately after escaping, because
    a wildcard search should highlight the whole matched word.
    """
    for term in terms:
        pattern = re.escape(term).replace(r'\*', '[a-zA-Z]*')
        text = re.sub(
            rf'\b{pattern}\b',
            r'<b style="color:#990000">\g<0></b>',
            text,
            flags=re.IGNORECASE
        )
    return text


# ------------------------------------------------------------------------------
# Which page did the visitor ask for?
#
# CGI reads query string parameters from the environment. int() with a fallback
# is what keeps a hand-edited ?page=DROP+TABLE out of the LIMIT clause.
# ------------------------------------------------------------------------------

params = parse_qs(os.environ.get('QUERY_STRING', ''))

try:
    current_page = int(params.get('page', ['1'])[0])
except ValueError:
    current_page = 1

if current_page < 1:
    current_page = 1

keyword = 'love'
search_key, terms = parse_keywords(keyword)

verses = {}
count = 0

try:
    # Because of the Strong's coding in `kjvs` it is not usable for a
    # complicated search, so we use `kjv`, which holds the plain text.
    #
    # First pass: the total, so we know how many pages there are. This is the
    # cost of pagination -- two queries where an unpaginated page needs one.
    cursor.execute(f"SELECT COUNT(`id`) AS `total` FROM `kjv` WHERE {search_key}")
    count = cursor.fetchone()['total']

    pages = math.ceil(count / PER_PAGE) if count else 1

    # Now that the total is known, a page number past the end can be clamped.
    if current_page > pages:
        current_page = pages

    start = (current_page - 1) * PER_PAGE

    # Second pass: only the rows this page needs. start is an integer by
    # construction above, so it is safe in the LIMIT clause.
    cursor.execute(f"SELECT * FROM `kjv` WHERE {search_key} LIMIT {start}, {PER_PAGE}")

    for row in cursor.fetchall():
        vid = row['id']
        ref = f"{row['book']} {row['chapter']}:{row['verse']}"
        text = re.sub(r'\{(.*?)\}', '', row['text'])    # Filter out the Strong's numbers
        verses[vid] = {'ref': ref, 'text': text}

except Exception as e:
    print(f"<p>Error: {e}</p>")
    cursor.close()
    conn.close()
    exit()


def pagination(current_page, pages):
    """Build the page links.

    With 'love' there are far too many pages to list them all, so the list is
    truncated to a window around the current page, with arrows at each end.

        [<] [3] [4] [5] [-6-] [7] [8] [9] [>]

    The window is nine numbers wide, so there is room for four on either side of
    the current page. An odd width keeps it symmetrical; an even one would leave
    the current page off centre.
    """
    if pages < 2:
        return ''

    first = current_page - (WINDOW // 2)
    if first < 1:
        first = 1

    last = first + WINDOW - 1
    if last > pages:
        last = pages
        first = max(1, last - WINDOW + 1)

    links = ''

    if first > 1:
        links += f'    <a href="?page={first - 1}" class="pagination page">&laquo;</a>\n'

    for p in range(first, last + 1):
        # The PHP compares a zero-based page counter against these one-based
        # numbers, so no link ever gets the active class. Here both are
        # one-based and the current page is marked correctly.
        active = ' active' if p == current_page else ''
        links += (f'    <a href="?page={p}" class="pagination page num{active}">'
                  f'<span>{p}</span></a>\n')

    if last < pages:
        links += f'    <a href="?page={last + 1}" class="pagination page">&raquo;</a>\n'

    return f'  <div class="pagination row">\n{links}  </div>\n'


# ------------------------------------------------------------------------------
# Output
# ------------------------------------------------------------------------------

start = (current_page - 1) * PER_PAGE
begin = start + 1                                   # We count from zero, humans count from one
end = min(start + PER_PAGE, count)                  # The last page is usually short

nav = pagination(current_page, pages)

print('<div style="float:left;width:450px;margin-left:30px;padding:0 15px;'
      'margin-bottom:10px;border:1px solid #000">')

print(f'  <div style="width:450px;margin-bottom:20px;">'
      f'<h3>{begin} - {end} of {count} verses containing the word '
      f'&ldquo;{keyword}&rdquo;</h3></div>')

results = ''
for verse in verses.values():
    text = highlight(verse['text'], terms)
    results += (f'  <div style="width:450px;margin-bottom:20px;margin-top:20px">\n'
                f'    &ldquo;{text}&rdquo;&mdash;{verse["ref"]}\n  </div>\n')

print(nav + results + nav)                          # Links above and below the results
print('</div>')

cursor.close()
conn.close()

print("""
<style>
    .pagination.row {
        text-align:center;
        padding:5px;
        }
    .pagination.page {
        display:inline-block;
        width:30px;
        height:25px;
        border:1px solid #000;
        background-color:#ccc;
        text-align:center;
        padding-top:5px;
        color:#000;
        text-decoration:none;
        font-weight:bold;
        font-family:arial;
        margin-top:-3px;
    }
    .pagination.page:hover {
        background-color:#fff;
    }
    .pagination.num span {
        display:block;
    }
    .pagination.num.active {
        background-color:#aaaaff;
    }
</style>
""")
