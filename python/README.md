# Open Source Bible Project &mdash; Python Scripts

These are samples of Python scripts to manage the data and display the results. They mirror the functionality of the PHP scripts in the `/php` folder.

## Environment Used

These scripts were developed and tested using **CGI on XAMPP for Windows**. This is a non-standard Python web setup, so several things in these scripts are specific to that environment.

### Shebang Line

Each script begins with:

```python
#!C:/xampp/python/python.exe
```

This tells Apache where to find the Python interpreter. On other systems this will differ:

- **Linux/macOS:** `#!/usr/bin/env python3` or `#!/usr/bin/python3`
- **Virtual environment:** `#!/path/to/venv/bin/python`
- **Other Windows installs:** `#!C:/Python312/python.exe` (or wherever Python is installed)

If you're not using CGI (e.g. Flask, Django, FastAPI), the shebang line is irrelevant.

### CGI Headers

Each script manually prints HTTP headers before any output:

```python
print("Content-Type: text/html\n")       # HTML scripts
print("Content-Type: application/json\n") # AJAX/JSON scripts
```

The blank line after the header (`\n`) is required by CGI protocol. Frameworks handle this automatically, so if you're adapting these scripts to Flask, Django, etc., remove these print statements and use the framework's response objects instead.

### Apache Configuration

For CGI execution on XAMPP, Apache needs:

1. `mod_cgi` or `mod_cgid` enabled
2. A `ScriptAlias` or `ExecCGI` option for the directory containing the `.py` files
3. The `.py` extension associated with the Python interpreter

A typical `httpd.conf` addition:

```apache
<Directory "/path/to/python/scripts">
    Options +ExecCGI
    AddHandler cgi-script .py
</Directory>
```

### Query Parameters (Scripts 5 and 8)

CGI scripts read query string parameters from environment variables:

```python
import os
from urllib.parse import parse_qs

query_string = os.environ.get('QUERY_STRING', '')
params = parse_qs(query_string)
keyword = params.get('keyword', ['default'])[0]
```

In Flask this would be `request.args.get('keyword')`. In Django, `request.GET.get('keyword')`.

Script 8 reads `page` the same way. Note that it casts the value to `int` before the number reaches the `LIMIT` clause &mdash; a page number arrives as text from the visitor, and that cast is the whole defense.

Script 8's page links are relative (`?page=2`), so the script works whether it is reached as `8.%20Pagination.py` or through a rewrite. The PHP original hard-codes its own filename into every link.

## Database

All scripts use **pymysql** to connect to a local MySQL database named `osbible`:

```python
import pymysql

conn = pymysql.connect(
    host='localhost',
    user='db_user',
    password='db_password',
    database='osbible',
    cursorclass=pymysql.cursors.DictCursor
)
```

`DictCursor` returns rows as dictionaries (equivalent to PHP's `mysqli_fetch_assoc`). The credentials shown are placeholders &mdash; use proper credentials for your setup.

### Alternatives to pymysql

- **mysql-connector-python** &mdash; Oracle's official connector. Syntax is very similar.
- **SQLAlchemy** &mdash; ORM layer. Overkill for these examples but common in production.
- **sqlite3** &mdash; If you want to skip MySQL entirely, Python's built-in SQLite module works, but you'd need to convert the database and adjust any MySQL-specific SQL (like `REGEXP`).

## Word Boundaries &mdash; Read This Before Running Scripts 6, 7 and 8

MariaDB and MySQL 5.7 and earlier use POSIX word boundaries in `REGEXP`:

```sql
`text` REGEXP '[[:<:]]love[[:>:]]'
```

MySQL 8.0.4 replaced the regex engine with ICU, which uses `\b` instead and rejects the POSIX form outright with a *repetition-operator operand invalid* error.

The PHP originals were written against MariaDB 10.4. They now carry the same `$WB_START`/`$WB_END` switch, so all three languages default to the MySQL 8 form. If you get a regex error, change this pair of lines near the top of scripts 6, 7 and 8:

```python
WB_START = '\\b'                    # MariaDB / MySQL 5.7:  '[[:<:]]'
WB_END = '\\b'                      # MariaDB / MySQL 5.7:  '[[:>:]]'
```

One subtlety: a lone backslash inside a MySQL string literal is an escape character, so `\b` has to reach the server doubled or it is read as a backspace. Where the pattern is passed as a `%s` parameter (script 6), pymysql handles that. Where the condition is built as text (scripts 7 and 8), `sql_quote()` does it.

## Security Note

Scripts 7 and 8 build a `WHERE` clause of unknown shape &mdash; one `REGEXP` condition per search term. A `%s` placeholder can stand in for a value but not for a fragment of SQL, so that fragment is assembled as text and quoted with `sql_quote()`, which wraps `pymysql`'s `escape_string()`.

That is the one place in the set where escaping is ours to get right. The PHP originals had the same exposure and did not escape there at all &mdash; worse, they called `sprintf()` with `mysqli_real_escape_string()` as the argument while the format string held no `%s`, so the escaped value went nowhere and the terms reached the query raw. That has since been corrected in `/php` as well. Do not copy the pattern for anything taking real visitor input without thinking it through &mdash; prefer MySQL full-text search, or check the terms against a strict allowlist first.

Script 8 also uses `re.escape()` before building the highlighter's pattern, so a search term containing a regex character cannot produce a broken pattern. The PHP dropped the keyword straight into `preg_replace`; it now uses `preg_quote()`.

## Adapting to Other Frameworks

If you're using these scripts as a starting point for a framework-based project, the core logic (SQL queries, result processing, regex operations) stays the same. What changes:

| CGI Approach | Framework Equivalent |
|---|---|
| `print("Content-Type: ...")` | Framework handles headers |
| `print(output)` | Return response object |
| `os.environ['QUERY_STRING']` | `request.args` / `request.GET` |
| Shebang line | Not needed |
| Script = one URL | Route decorator maps URL to function |

## Script Summary

1. **Load a Single Bible Verse** &mdash; Basic database connection and single verse retrieval.
2. **Load Several Verses** &mdash; Fetch multiple verses from a chapter range.
3. **Catch Various Book Reference Types** &mdash; Handle abbreviations, misspellings, and ordinal variations in book names.
4. **Catch Various Number Reference Types** &mdash; Parse verse ranges, comma-separated verses, multi-chapter references.
5. **AJAX Response** &mdash; Accept a query parameter and return JSON for use with JavaScript front-ends.
6. **Find Verses Containing a Key Word** &mdash; Search verse text using MySQL `REGEXP` with word boundary matching.
7. **Find Verses Containing Multiple Key Words** &mdash; Quoted phrases, wildcards, and multiple terms that must all appear in the same verse.
8. **Pagination** &mdash; Page through a long result set: a `COUNT` query for the total, a `LIMIT` query for the page, and a truncated window of page links.

### Departures from the PHP Originals

These were written as corrections to the PHP, and have since been applied to `/php` as well, so the two now agree.

- **Script 8's active page link is fixed.** The PHP compared a zero-based page counter against one-based page numbers, so no link ever received the `active` class. Both are one-based now.
- **Script 8's page links are relative** rather than carrying a hard-coded filename.
- **Escaping added** in scripts 7 and 8 (`sql_quote`) and in script 8's highlighter (`re.escape`).
- **Word boundary syntax is configurable** rather than hard-coded to the MariaDB form.
- Scripts 6, 7 and 8 use `kjv` or `kjvs` as the PHP does &mdash; `kjvs` carries inline Strong's numbers, which make it unusable for a text search, so the search scripts read `kjv`.

## Requirements

- Python 3.6+
- pymysql (`pip install pymysql`)
- The `osbible` MySQL database (see `/mysql` for setup)
