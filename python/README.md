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

### Query Parameters (Script 5)

CGI scripts read query string parameters from environment variables:

```python
import os
from urllib.parse import parse_qs

query_string = os.environ.get('QUERY_STRING', '')
params = parse_qs(query_string)
keyword = params.get('keyword', ['default'])[0]
```

In Flask this would be `request.args.get('keyword')`. In Django, `request.GET.get('keyword')`.

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

## Requirements

- Python 3.6+
- pymysql (`pip install pymysql`)
- The `osbible` MySQL database (see `/mysql` for setup)
