/*************************************************************************************
 *
 * 	server.js — runs all the lesson scripts at once.
 *
 * 	Run it with:  node server.js
 * 	Then open:    http://localhost:3000
 *
 * 	Each numbered script in this folder is a complete, self-contained lesson that
 * 	you can run on its own. This file is the ninth piece: it requires them all and
 * 	routes between them, so you can browse the whole set without stopping and
 * 	restarting for each one.
 *
 * 	It is also the honest answer to "how would this actually be deployed?"
 * 	Nobody runs eight Node processes on eight ports. A real deployment is one
 * 	process with several routes — which is exactly what PHP hands you for free
 * 	by mapping URLs onto files, and what Node makes you write yourself.
 *
 * 	Note there is no framework here. Express would make the routing shorter, but
 * 	it would also mean explaining Express before explaining the Bible query. The
 * 	built-in http module is enough for nine routes.
 *
 ***************************************************************************************/

const http = require('http');

/*******************************
 *
 * The route table.
 *
 * Add a line here as each lesson script is written. The `path` is the URL, the
 * `title` is what shows on the index page, and `module` is the script itself —
 * required once at startup, not on every request.
 *
 * Each lesson exports a handleRequest(req, res) function. Requiring the file
 * does NOT start a server, because of the `require.main === module` check at the
 * bottom of each one.
 *
 *****************************/

const Routes = [
	{ path: '/1', title: 'Load a Single Verse',                        file: './1. Load a Single Verse.js' },
	{ path: '/2', title: 'Load Several Verses',                        file: './2. Load Several Verses.js' },
	{ path: '/3', title: 'Catch Various Book Reference Types',         file: './3. Catch Various Book Reference Types.js' },
	{ path: '/4', title: 'Catch Various Number Reference Types',       file: './4. Catch Various Number Reference Types.js' },
	{ path: '/5', title: 'AJAX Response',                              file: './5. AJAX Response.js',
	  hint: 'returns raw JSON, so no back link — try <a href="/5?keyword=Eph+2:8-10">/5?keyword=Eph+2:8-10</a>' },
	{ path: '/6', title: 'Find Verses Containing a Key Word',          file: './6. Find Verses Containing a Key Word.js' },
	{ path: '/7', title: 'Find Verses Containing Multiple Key Words',  file: './7. Find Verses Containing Multiple Key Words.js' },
	{ path: '/8', title: 'Pagination',                                 file: './8. Pagination.js' }
];

// Load each lesson once at startup and attach its handler to the route.
for (const route of Routes) {
	route.handler = require(route.file).handleRequest;
}

/*******************************
 *
 * The index page.
 *
 * Built from the route table, so adding a lesson above adds it here too.
 *
 *****************************/

function indexPage() {

	const links = Routes
		.map(r => `\t\t<li><a href="${r.path}">${r.path.slice(1)}. ${r.title}</a>` +
		          (r.hint ? ` <span class="hint">${r.hint}</span>` : '') + `</li>`)
		.join('\n');

	return `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>osBible &mdash; Node.js Scripts</title>
<style>
	body { font: 16px/1.6 system-ui, sans-serif; margin: 2rem auto; max-width: 42rem; padding: 0 1rem; color: #222; }
	h1   { font-size: 1.4rem; margin-bottom: .25rem; }
	p.sub{ color: #777; margin-top: 0; }
	ol, ul { padding-left: 1.2rem; }
	li   { margin: .4rem 0; }
	a    { color: #0b5ed7; text-decoration: none; }
	a:hover { text-decoration: underline; }
	code { background: #f4f4f4; padding: .1rem .3rem; border-radius: 3px; font-size: .9em; }
	.hint { color: #777; font-size: .85em; }
</style>
</head>
<body>
<h1>osBible &mdash; Node.js Scripts</h1>
<p class="sub">Each of these is also runnable on its own, e.g. <code>node "1. Load a Single Verse.js"</code></p>
<ul>
${links}
</ul>
</body>
</html>`;
}

/*******************************
 *
 * The server.
 *
 *****************************/

const server = http.createServer((req, res) => {

	// Strip any query string; the lesson scripts read that themselves where
	// they need it (script 5 in particular).
	const path = req.url.split('?')[0];

	// Browsers ask for this on every page load. Answer it once here rather than
	// in every lesson script.
	if (path === '/favicon.ico') {
		res.writeHead(204);
		return res.end();
	}

	if (path === '/' || path === '') {
		res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
		return res.end(indexPage());
	}

	const route = Routes.find(r => r.path === path);

	if (!route) {
		res.writeHead(404, { 'Content-Type': 'text/html; charset=utf-8' });
		return res.end('<p>No such script. <a href="/">Back to the list</a></p>');
	}

	// Hand off to the lesson. Its own try/catch reports errors, but an
	// unhandled rejection here would take the whole process down, so catch it.
	Promise.resolve(route.handler(req, res)).catch(err => {
		if (!res.headersSent) {
			res.writeHead(500, { 'Content-Type': 'text/html; charset=utf-8' });
		}
		res.end(`<p>Error: ${err.message}</p>`);
	});
});

server.listen(3000, () => {
	console.log('osBible Node scripts running on http://localhost:3000  (Ctrl+C to stop)');
	console.log(`${Routes.length} script${Routes.length === 1 ? '' : 's'} mounted.`);
});
