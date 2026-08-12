/*************************************************************************************
 *
 * 	This script is to demonstrate basic pagination to allow the site visitor to
 * 		navigate through a lengthy result.
 *
 * 	Run it on its own:   node "8. Pagination.js"    then open localhost:3000
 * 	Or run them all:     node server.js             then open localhost:3000/8
 *
 * 	The page links are relative (?page=2) rather than naming a file, so they work
 * 	the same whether this script is running on its own or mounted under server.js.
 *
 ***************************************************************************************/

const http = require('http');
const mysql = require('mysql2/promise');
const { dbConfig, backLink, parseKeywords, stripStrongs, escapeRegExp } = require('./bible.js');

const PER_PAGE = 10;		// This can be changed here, or even by the visitor if you allow it.
const WINDOW   = 9;			// How many numbered links to show at once.

function highlight(text, terms) {
	for (const term of terms) {
		const pattern = escapeRegExp(term).replace(/\\\*/g, '[a-zA-Z]*');
		text = text.replace(
			new RegExp(`\\b${pattern}\\b`, 'gi'),
			'<b style="color:#990000">$&</b>'
		);
	}
	return text;
}

/*******************************
 *
 * buildPagination — the page links.
 *
 * With the keyword "love" there are around 30 pages, which is too many to show at
 * once, so we show a window of them.
 *
 * If we're near the start we show pages 1 to 9 and a next arrow:
 * 	[-1-][2][3][4][5][6][7][8][9][»]
 * Once we've moved into the list we show a previous arrow, four pages either side
 * of the current page, and a next arrow:
 * 	[«][3][4][5][-6-][7][8][9][10][»]
 * Near the end the window stops sliding and sits against the last page.
 *
 * The current page plus the pages on either side has to be an odd number or the
 * window ends up lopsided — which is why WINDOW is 9 rather than 10.
 *
 *****************************/

function buildPagination(currentPage, totalPages) {

	if (totalPages <= 1) { return ''; }

	// Centre the window on the current page, then push it back inside the range.
	let begin = currentPage - Math.floor(WINDOW / 2);
	if (begin < 1) { begin = 1; }

	let end = begin + WINDOW - 1;
	if (end > totalPages) {
		end   = totalPages;
		begin = Math.max(1, totalPages - WINDOW + 1);
	}

	let out = '<div class="pagination row">';

	if (begin > 1) {
		out += `<a href="?page=${begin - 1}" class="pagination page">&laquo;</a>\n`;
	}

	for (let p = begin; p <= end; p++) {
		const active = (p === currentPage) ? ' active' : '';
		out += `<a href="?page=${p}" class="pagination page num${active}"><span>${p}</span></a>\n`;
	}

	if (end < totalPages) {
		out += `<a href="?page=${end + 1}" class="pagination page">&raquo;</a>\n`;
	}

	return out + '</div>\n';
}

async function paginate(page) {

	const db = await mysql.createConnection(dbConfig);

	try {

		const keyword = 'love';

		// The page number comes from the query string, so force it to a whole
		// number at least 1. The PHP casts to an integer for the same reason —
		// this value goes into a LIMIT clause, where a placeholder cannot be used.
		const currentPage = Math.max(1, parseInt(page, 10) || 1);
		const start = (currentPage - 1) * PER_PAGE;

		const { searchKey, terms } = parseKeywords(keyword);

		/*******************************
		 *
		 * First the total, so we know how many pages there are.
		 *
		 * Because of the Strong's coding in `kjvs` it's not usable for this kind of
		 * search, so we use `kjv`, which holds the plain text.
		 *
		 *****************************/

		const [countRows] = await db.query(
			`SELECT COUNT(\`id\`) AS \`total\` FROM \`kjv\` WHERE ${searchKey}`
		);
		const count = countRows[0].total;

		const totalPages = Math.ceil(count / PER_PAGE) || 1;

		// Now just the few specified by the start and the length.
		// LIMIT takes ? placeholders in mysql2, unlike the WHERE fragment above.
		const [rows] = await db.query(
			`SELECT * FROM \`kjv\` WHERE ${searchKey} LIMIT ?, ?`,
			[start, PER_PAGE]
		);

		const Verses = rows.map(row => ({
			// `kjv` carries the book name directly
			ref:  `${row.book} ${row.chapter}:${row.verse}`,
			text: stripStrongs(row.text)
		}));

		/*******************************
		 *
		 * Output.
		 *
		 *****************************/

		const begin = count ? start + 1 : 0;		// humans count from one
		const shown = Math.min(start + PER_PAGE, count);

		let results = '\n';
		for (const Verse of Verses) {
			const text = highlight(Verse.text, terms);
			results += `  <div style="width:450px;margin-bottom:20px;margin-top:20px">\n` +
			           `    &ldquo;${text}&rdquo;&mdash;${Verse.ref}\n  </div>\n`;
		}

		const pagination = buildPagination(currentPage, totalPages);

		return `<h1>Pagination.</h1>
<div style="float:left;width:450px;margin-left:30px;padding:0 15px;margin-bottom:10px;border:1px solid #000">
  <div style="width:450px;margin-bottom:20px;">
    <h3>${begin} - ${shown} of ${count} verses containing the word &ldquo;${keyword}&rdquo;</h3>
  </div>
${pagination}${results}${pagination}</div>
<style>
	.pagination.row    { text-align:center; padding:5px; }
	.pagination.page   { display:inline-block; width:30px; height:25px;
	                     border:1px solid #000; background-color:#ccc;
	                     text-align:center; padding-top:5px; color:#000;
	                     text-decoration:none; font-weight:bold;
	                     font-family:arial; margin-top:-3px; }
	.pagination.page:hover  { background-color:#fff; }
	.pagination.num span    { display:block; }
	.pagination.num.active  { background-color:#aaaaff; }
</style>`;

	} finally {
		await db.end();
	}
}

async function handleRequest(req, res) {

	const url = new URL(req.url, 'http://localhost');
	const page = url.searchParams.get('page') || 1;

	try {
		const output = await paginate(page);
		res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
		res.end(backLink() + output);
	} catch (err) {
		res.writeHead(500, { 'Content-Type': 'text/html; charset=utf-8' });
		res.end(`<p>Error: ${err.message}</p>`);
	}
}

module.exports = { handleRequest, paginate };

if (require.main === module) {
	http.createServer((req, res) => {
		if (req.url === '/favicon.ico') { res.writeHead(204); return res.end(); }
		handleRequest(req, res);
	}).listen(3000, () => {
		console.log('Listening on http://localhost:3000  (Ctrl+C to stop)');
	});
}
