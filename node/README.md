# Open Source Bible Project &mdash; Node.js Scripts

These are samples of Node.js scripts to manage the data and display the results. They mirror the functionality of the PHP scripts in the `/php` folder.

## Getting Started

```bash
cd node
npm install
node server.js
```

Then open <http://localhost:3000>.

`node_modules/` is not in the repository. `npm install` reads `package.json` and `package-lock.json` and rebuilds it.

Each numbered script is also runnable on its own:

```bash
node "1. Load a Single Verse.js"
```

That also listens on port 3000, so stop `server.js` first. A standalone script answers every path with the same page &mdash; there is no routing in it.

## Environment Used

Developed and tested on **Node.js v22 LTS** on Windows, against the same MySQL 8 database the PHP and Python scripts use.

### No Framework

There is no Express, no Fastify, nothing. `server.js` uses the built-in `http` module. Express would make the routing shorter, but it would mean explaining Express before explaining the Bible query, and the whole point of these samples is the query.

If you are porting to Express, the only file that changes structurally is `server.js`. The lesson scripts each export a `handleRequest(req, res)` that maps onto a route handler with almost no edits.

### CommonJS, not ESM

`package.json` sets `"type": "commonjs"`, so these use `require()` and `module.exports`. To convert to ESM, change that to `"module"`, rename the `require` calls to `import`, and replace the `require.main === module` checks (see below) with `import.meta.url` comparisons.

### The `require.main === module` Check

Every lesson script ends with:

```js
if (require.main === module) {
    // start a server on port 3000
}
```

`require.main` is the file Node was launched with. Run the script yourself and it equals that module, so the server starts. When `server.js` requires it instead, it does not &mdash; which is what stops eight lesson files from all trying to claim port 3000 at once.

`backLink()` in `bible.js` uses the same test in reverse: the "back to the main page" link renders only when `server.js` is what is running, because in standalone mode there is no index page to go back to.

### Query Parameters (Script 5)

The built-in `http` module gives you the raw URL and nothing else, so parsing is manual:

```js
const { searchParams } = new URL(req.url, 'http://localhost');
const keyword = searchParams.get('keyword') || 'John 3:16';
```

In Express this would be `req.query.keyword`. In PHP it is `$_GET['keyword']`.

## Database

All scripts use **mysql2** with its promise API. The connection settings live once in `bible.js`:

```js
const dbConfig = {
    host:     'localhost',
    user:     'db_user',
    password: 'db_password',
    database: 'osbible',
    charset:  'utf8mb4'
};
```

The credentials shown are what was used on a local intranet for demonstration. Use proper credentials for your setup, and keep them out of version control &mdash; environment variables or a config file listed in `.gitignore`.

Queries use `?` placeholders, which the driver escapes:

```js
const [rows] = await db.query(
    'SELECT * FROM `kjvs` WHERE `book` = ? AND `chapter` = ? AND `verse` = ?',
    [bid, chapter, verse]
);
```

The destructuring is mysql2's doing &mdash; `query()` resolves to `[rows, fields]`.

### Alternatives to mysql2

- **mysql** &mdash; the older driver. Callback-based unless you wrap it; mysql2 is a drop-in replacement with prepared statement support.
- **Knex** &mdash; query builder. Useful if you want the same code to run against MySQL, Postgres and SQLite.
- **Prisma / Sequelize** &mdash; ORM layer. Overkill for these examples but common in production.

## Word Boundaries &mdash; Read This Before Running Scripts 6, 7 and 8

MariaDB and MySQL 5.7 and earlier use POSIX word boundaries in `REGEXP`:

```sql
`text` REGEXP '[[:<:]]love[[:>:]]'
```

MySQL 8.0.4 replaced the regex engine with ICU, which uses `\b` instead and rejects the POSIX form outright with a *repetition-operator operand invalid* error.

The PHP originals were written against MariaDB 10.4. They now carry the same `$WB_START`/`$WB_END` switch, so all three languages default to the MySQL 8 form. If you get a regex error, this is the pair of lines to change, near the top of `bible.js`:

```js
const WB_START = '\\b';     // MariaDB / MySQL 5.7:  '[[:<:]]'
const WB_END   = '\\b';     // MariaDB / MySQL 5.7:  '[[:>:]]'
```

## Shared Code: `bible.js`

The PHP originals repeat `getBookByKeyword()`, `putNumRefInArray()` and `getVerseCount()` in full inside scripts 3, 4 and 5 &mdash; the same 120 lines copied three times. PHP has includes, so that was a choice rather than a limitation, but a fix in one copy has to be remembered in the other two.

In the Node version those live once in `bible.js` and are required where needed. The PHP versions also reach the database through `global $_mysql`; here the connection is passed in as an argument so it is obvious what each function touches.

`bible.js` exports:

| Function | Purpose |
|---|---|
| `dbConfig` | Connection settings |
| `getBooks` | Book id &harr; book name lookups |
| `getBookByKeyword` | Resolve whatever the visitor typed to a book |
| `getVerseCount` | Verses in a given chapter |
| `putNumRefInArray` | Split the numeric part of a reference into chapter/verse pairs |
| `expandVerseList` | `"8-10,12"` &rarr; `[8,9,10,12]` |
| `stripStrongs` | Remove inline `{H430}` Strong's numbers |
| `parseKeywords` | Build the search WHERE clause and the highlight terms |
| `wordBoundary` | Wrap a term per the `WB_START`/`WB_END` setting |
| `escapeRegExp` | Make a visitor-supplied string safe to build a `RegExp` from |
| `sqlQuote` | Quote a string for SQL &mdash; see the warning below |
| `backLink` | Link back to the index, when running under `server.js` |

## Security Note

`parseKeywords()` builds a `WHERE` clause of unknown shape &mdash; one `REGEXP` condition per search term. A `?` placeholder can stand in for a value but not for a fragment of SQL, so that fragment is assembled as text and quoted with `sqlQuote()`.

That is the one place in the whole set where escaping is ours to get right. The PHP originals had the same exposure and did not escape there at all &mdash; worse, they called `sprintf()` with `mysqli_real_escape_string()` as the argument while the format string held no `%s`, so the escaped value went nowhere and the terms reached the query raw. That has since been corrected in `/php` as well. Do not copy the pattern for anything taking real visitor input without thinking it through &mdash; prefer MySQL full-text search, or validate terms against a strict allowlist first.

Two smaller fixes, both since carried back into the PHP:

- `escapeRegExp()` &mdash; the PHP dropped the keyword straight into `preg_replace`, so a keyword containing a regex character produced a broken pattern or an error. JavaScript has the same exposure, so the term is escaped before the highlighter builds a `RegExp` from it.
- Script 8's pagination marks the correct page as active. The PHP compared a zero-based page counter against one-based page numbers, so no link ever received the class.

## Adapting to a Framework

The core logic &mdash; SQL, result processing, reference parsing &mdash; stays the same. What changes:

| Built-in `http` | Express Equivalent |
|---|---|
| `res.writeHead(200, {...})` | `res.type('html')` |
| `res.end(output)` | `res.send(output)` |
| `new URL(req.url, ...).searchParams` | `req.query` |
| Route table in `server.js` | `app.get('/1', handler)` |
| `require.main === module` guard | Not needed |

## Script Summary

1. **Load a Single Verse** &mdash; Basic connection and single verse retrieval.
2. **Load Several Verses** &mdash; Fetch multiple verses from a chapter range.
3. **Catch Various Book Reference Types** &mdash; Abbreviations, misspellings, ordinals and Roman numerals in book names.
4. **Catch Various Number Reference Types** &mdash; Verse ranges, comma-separated verses, multi-chapter references.
5. **AJAX Response** &mdash; Accept a query parameter and return JSON for a JavaScript front end. Try `/5?keyword=Eph+2:8-10`.
6. **Find Verses Containing a Key Word** &mdash; Search verse text using `REGEXP` with word boundary matching.
7. **Find Verses Containing Multiple Key Words** &mdash; Quoted phrases, wildcards, and multiple terms that must all appear.
8. **Pagination** &mdash; Page through a large result set.

Plus two files that are not lessons in themselves:

- **bible.js** &mdash; the shared helpers listed above.
- **server.js** &mdash; mounts all eight at `/1` through `/8` with an index page. It is also the honest answer to "how would this be deployed?" Nobody runs eight Node processes on eight ports; a real deployment is one process with several routes, which is exactly what PHP hands you for free by mapping URLs onto files and what Node makes you write yourself.

## Requirements

- Node.js 18+ (developed on v22 LTS)
- mysql2 (`npm install`)
- The `osbible` MySQL database (see `/mysql` for setup)
