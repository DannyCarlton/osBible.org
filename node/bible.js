/*************************************************************************************
 *
 * 	bible.js — shared reference-parsing helpers.
 *
 * 	The PHP originals repeat getBookByKeyword(), putNumRefInArray() and
 * 	getVerseCount() in full inside scripts 3, 4 and 5 — the same 120 lines copied
 * 	three times. PHP has includes, so that was a choice rather than a limitation,
 * 	but it does mean a fix in one copy has to be remembered in the other two.
 *
 * 	Node's module system makes sharing them the obvious move: written once here,
 * 	required where needed. This is one of the few places where the Node version is
 * 	genuinely better structured than the original rather than just different.
 *
 * 	Another difference worth noting: the PHP versions reach the database through
 * 	`global $_mysql`. Globals are available in Node too, but passing the
 * 	connection in as an argument makes it obvious what each function touches.
 *
 ***************************************************************************************/

/*******************************
 *
 * Word-boundary syntax for REGEXP — read this before running scripts 6, 7 or 8.
 *
 * MariaDB (and MySQL 5.7 and earlier) use POSIX word boundaries: [[:<:]]word[[:>:]]
 * MySQL 8.0.4 and later replaced the regex engine with ICU, which uses \b instead
 * and rejects the POSIX form outright with a "repetition-operator operand invalid"
 * error.
 *
 * The PHP scripts were written against MariaDB 10.4 and use the POSIX form. These
 * scripts default to the MySQL 8 form. If you get a regex error, this is the line
 * to change.
 *
 *****************************/

const WB_START = '\\b';		// MariaDB / MySQL 5.7:  '[[:<:]]'
const WB_END   = '\\b';		// MariaDB / MySQL 5.7:  '[[:>:]]'

// Wrap a search term in whichever word-boundary syntax is set above.
function wordBoundary(term) {
	return WB_START + term + WB_END;
}

/*******************************
 *
 * Standard connection settings, in one place so eight scripts don't each carry
 * their own copy.
 *
 * For demonstration purposes I actually used db_user and db_password for my local
 * intranet. You'll want to use something more secure. The database is named
 * `osbible` (Yours may be different.)
 *
 *****************************/

const dbConfig = {
	host:     'localhost',
	user:     'db_user',
	password: 'db_password',
	database: 'osbible',
	charset:  'utf8mb4'
};

/*******************************
 *
 * getBooks — book id to book name.
 *
 * Script 6 needs this because `kjvs` stores the book as a number.
 *
 *****************************/

async function getBooks(db) {
	const [rows] = await db.query('SELECT * FROM `kjv_books`');

	const bookName = new Map();		// book id   -> book name
	const Bids     = new Map();		// book name -> book id
	const Books    = new Map();		// book id   -> whole row

	for (const row of rows) {
		bookName.set(row.id, row.book);
		Bids.set(row.book, row.id);
		Books.set(row.id, row);
	}

	return { bookName, Bids, Books };
}

/*******************************
 *
 * getBookByKeyword — take whatever the visitor typed as the book and work out
 * which book they meant, whether it's the full name, an abbreviation, an ordinal,
 * a Roman numeral or a misspelling.
 *
 *****************************/

async function getBookByKeyword(db, k) {

	const Return = { bid: 0, book: '', chapters: 0, num_key: '' };

	// Clean any oddities brought over in a url
	let _k = decodeURIComponent(k);

	// Remove extra spaces
	_k = _k.replace(/\s{2,}/g, ' ');

	// Change periods to colons, so "John 3.16" becomes "John 3:16"
	_k = _k.replace(/\./g, ':');

	// Remove spaces from the verse separator (;)
	_k = _k.replace(/; /g, ';');

	// Make ordinals regular numbers.
	// Note: here we are expecting a space between the ordinal and the book name.
	_k = _k.replace(/1st /g, '1 ')
	       .replace(/2nd /g, '2 ')
	       .replace(/3rd /g, '3 ')
	       .replace(/first /g,  '1 ')
	       .replace(/second /g, '2 ')
	       .replace(/third /g,  '3 ');

	// Roman numerals. The /i catches upper and lower case instances.
	// Order matters: iii must be tested before ii, and ii before i, or "iii "
	// would match the /^i / pattern first and become "1 ii ".
	_k = _k.replace(/^iii /i, '3 ')
	       .replace(/^ii /i,  '2 ')
	       .replace(/^i /i,   '1 ');

	// Catch messed up beginning numbers.
	// This catches initial numbers immediately followed by text, as in 1pe or 2jn
	if (/^[1-3][a-zA-Z]/.test(_k)) {
		_k = _k.replace(/^([1-3])/, '$1 ');
	}

	// Split by spaces
	const Ref_keys = _k.split(' ');

	// If the first element is empty, remove it
	// (this happens when the reference begins with a space)
	if (!Ref_keys[0]) {
		Ref_keys.shift();
	}

	let book_key, num_keys;

	// If the first element is a number, combine it with the second to make the
	// book key — "2 pt 1:1" has to become book "2 pt", numbers "1:1"
	if (Ref_keys[1] !== undefined && /^[1-9]/.test(Ref_keys[0])) {
		book_key = Ref_keys[0] + ' ' + Ref_keys[1];
		num_keys = Ref_keys[2];
	} else {
		book_key = Ref_keys[0];
		num_keys = Ref_keys[1];
	}

	// Add the chapter and verse(s) to the output as 'num_key'
	Return.num_key = num_keys || '';

	// I don't remember why, but I found this necessary
	if (book_key.toLowerCase() === 'jud') { book_key = 'Judges'; }
	if (book_key.toLowerCase() === 'eph') { book_key = 'Ephesians'; }

	/********************************************
	 *
	 * Let's explain the query...
	 * 	First we look in the `abbr` column for matches. The individual abbreviations
	 * 	are delimited by |
	 * 	We "score" the matches so that the one with the best "score" rises to the top
	 * 	and we get it. SOUNDS LIKE is MySQL's soundex comparison, which is what
	 * 	catches misspellings such as "gnesis" for Genesis.
	 *
	 * 	Every ? is the same book_key. The driver escapes each one, so unlike the
	 * 	PHP original there is no sprintf and no separate escape call per value.
	 *
	 *******************************************/

	const queryText = `
		SELECT * FROM \`kjv_books\`
		WHERE  \`abbr\`     LIKE CONCAT('%|', ?, '|%')
		   OR  \`book\`     LIKE ?
		   OR  \`kjav_abr\` LIKE ?
		   OR  \`book\`     SOUNDS LIKE ?
		ORDER BY
		     CASE WHEN \`abbr\`     LIKE CONCAT('%|', ?, '|%') THEN 4 ELSE 0 END
		   + CASE WHEN \`book\`     LIKE ?                     THEN 3 ELSE 0 END
		   + CASE WHEN \`kjav_abr\` LIKE ?                     THEN 2 ELSE 0 END
		   + CASE WHEN \`book\`     SOUNDS LIKE ?              THEN 1 ELSE 0 END
		DESC LIMIT 1`;

	const [rows] = await db.query(queryText, Array(8).fill(book_key));

	if (rows.length) {
		const row = rows[0];
		Return.bid      = row.id;
		Return.book     = row.book === 'Psalms' ? 'Psalm' : row.book;
		Return.chapters = row.chapters;
	}

	return Return;
}

/*******************************
 *
 * getVerseCount — how many verses are in a given chapter.
 *
 * Needed when someone asks for a whole chapter ("John 3") and we have to turn
 * that into a range.
 *
 *****************************/

async function getVerseCount(db, bid, chapter) {
	const [rows] = await db.query(
		'SELECT COUNT(`verse`) AS `versecount` FROM `kjvs` WHERE `book` = ? AND `chapter` = ?',
		[bid, chapter]
	);
	return rows[0].versecount;
}

/*******************************
 *
 * putNumRefInArray — turn the numeric part of a reference into a list of
 * chapter/verses pairs.
 *
 * Handles: a bare number ("3 John 3" or "John 3"), a single chapter:verse, and
 * multiple passages separated by semicolons.
 *
 *****************************/

async function putNumRefInArray(db, BookData) {

	const bid     = BookData.bid;
	let   num_key = BookData.num_key;
	let   Refs    = [];

	// Is it a single number? parseInt gives NaN for anything that isn't, and
	// String() back again lets us test whether the whole value was numeric.
	const _num_key = parseInt(num_key, 10);

	if (String(_num_key) === String(num_key)) {

		// If yes... does the book have a single chapter?
		if (BookData.chapters === 1) {
			// If yes, the number is the verse, so the ref is 1:n
			Refs = [`1:${_num_key}`];
		} else {
			// If no, then the ref is the whole chapter: c:1-(end of chapter)
			const verse_count = await getVerseCount(db, bid, num_key);
			Refs = [`${num_key}:1-${verse_count}`];
		}

	} else if (num_key.includes(';')) {

		// If a semi-colon is present, separate the passages.
		// Trailing semicolons produce an empty final element, so filter those out —
		// this is what catches "Luke 7:12; 8:42; 9:38;"
		Refs = num_key.split(';').filter(r => r.length);

	} else {
		Refs = [num_key];
	}

	// Separate the chapter from the verses in each passage.
	const numKeys = Refs.map(ref => {
		const [chapter, verses] = ref.split(':');
		return { chapter, verses };
	});

	return { Refs, numKeys };
}

/*******************************
 *
 * expandVerseList — turn "8", "8-10", "8,9,12" or "8-10,12" into [8,9,10,12].
 *
 * The PHP repeats this branching inline in scripts 4 and 5. It is the same logic
 * both times, so it lives here.
 *
 *****************************/

function expandVerseList(verses) {

	const verseList = [];

	const addRange = (piece) => {
		if (piece.includes('-')) {
			const [start, end] = piece.split('-').map(Number);
			for (let i = start; i <= end; i++) {
				verseList.push(i);
			}
		} else {
			verseList.push(piece);
		}
	};

	if (verses.includes(',')) {
		for (const piece of verses.split(',')) {
			addRange(piece);
		}
	} else {
		addRange(verses);
	}

	return verseList;
}

/*******************************
 *
 * stripStrongs — remove the Strong's numbers from a verse.
 *
 * The `kjvs` table stores them inline in braces: "In the beginning{H7225} God{H430}"
 *
 *****************************/

function stripStrongs(text) {
	return text.replace(/\{.*?\}/g, '');
}

/*******************************
 *
 * escapeRegExp — make a user-supplied string safe to build a RegExp from.
 *
 * The PHP drops the keyword straight into preg_replace, so a keyword containing
 * a regex character produces a broken pattern or an error. JavaScript has the
 * same exposure, so we escape first.
 *
 *****************************/

function escapeRegExp(s) {
	return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/*******************************
 *
 * parseKeywords — turn a search string into a SQL WHERE fragment and a list of
 * terms to highlight. Used by scripts 7 and 8.
 *
 * Handles three things at once:
 * 	"loved her"   a quoted phrase, which must survive being split on spaces
 * 	love*         a wildcard, which becomes [a-zA-Z]* for the REGEXP
 * 	with love     several words, all of which must appear in the verse
 *
 * The trick with phrases is the same one the PHP uses: temporarily swap the
 * space inside a quoted phrase for an underscore so the split doesn't break it
 * apart, then swap it back afterwards.
 *
 *****************************/

function parseKeywords(keyword) {

	let working = keyword;

	// Catch quotes that designate a phrase.
	// This expression pulls any words within quotes into Matches.
	if (working.includes('"')) {
		const Matches = [...working.matchAll(/"(.*?)"/g)].map(m => m[1]);

		for (const kw of Matches) {
			// Replace the space with an underscore so we can avoid it later —
			// 'David "loved her"' becomes 'David "loved_her"'
			const _kw = kw.trim().replace(/ /g, '_');
			working = working.replace(kw, _kw);
		}
		// remove the quotes
		working = working.replace(/"/g, '');
	}

	const SearchKey = [];	// the SQL conditions
	const terms     = [];	// the plain terms, for highlighting

	// Splitting on space handles both cases: one word gives a one-element list.
	for (let kw of working.split(' ')) {

		if (!kw) { continue; }

		// return any underscores to spaces
		kw = kw.replace(/_/g, ' ');

		// The term as the reader typed it, for the highlighter
		terms.push(kw);

		// change any asterisks to a usable regular expression
		const pattern = kw.replace(/\*/g, '[a-zA-Z]*');

		SearchKey.push('`text` REGEXP ' + sqlQuote(wordBoundary(pattern)));
	}

	// ...and link them with 'AND' operators, so every term must be present.
	return { searchKey: SearchKey.join(' AND '), terms };
}

/*******************************
 *
 * sqlQuote — quote a string for inclusion in SQL.
 *
 * Normally the ? placeholders handle this and we never write a function like
 * this. But parseKeywords builds a WHERE clause of unknown shape — one REGEXP
 * condition per search term — and a placeholder cannot stand in for a fragment
 * of SQL, only for a value. So that fragment has to be assembled as text.
 *
 * That makes this the one place in the whole set where escaping is ours to get
 * right. The PHP originals have the same exposure and do not escape here at all;
 * a keyword containing a quote will break the query, and a carefully chosen one
 * could do worse. Do not copy this pattern for anything that takes real visitor
 * input without thinking it through — prefer full-text search, or validate the
 * terms against a strict allowlist first.
 *
 *****************************/

function sqlQuote(s) {
	return "'" + String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
}

/*******************************
 *
 * backLink — a link back to the index that server.js builds.
 *
 * It renders only when server.js is the file Node was launched with. A lesson
 * script started on its own answers every path with the same page, so a link to
 * "/" there would simply reload what you are already looking at.
 *
 * Script 5 does not use this: its response is raw JSON, and an HTML link in the
 * middle of it would break every client trying to parse it.
 *
 *****************************/

function backLink() {
	if (!require.main || !require.main.filename.endsWith('server.js')) { return ''; }
	return '<p style="font:14px/1.5 system-ui,sans-serif;margin:0 0 1.2rem 30px">' +
	       '<a href="/" style="color:#0b5ed7;text-decoration:none">' +
	       '&laquo; Back to the main page</a></p>\n';
}

module.exports = {
	dbConfig,
	wordBoundary,
	backLink,
	getBooks,
	getBookByKeyword,
	getVerseCount,
	putNumRefInArray,
	expandVerseList,
	stripStrongs,
	escapeRegExp,
	parseKeywords,
	sqlQuote
};
