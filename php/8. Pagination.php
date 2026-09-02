<?php 
error_reporting(E_ALL);
echo "<h1>Pagination.</h1>\n";
/*************************************************************************************
 * 
 * 	This script is to demonstrate basic pagination to allow the site visitor to navigate 
 * 		through a lengthy result.
 * 
 * 	The search itself is the same one script 7 does. What is new here is counting the whole
 * 		result, asking for only one page of it, and building the page links.
 * 
 ***************************************************************************************/


/*******************************
 * 
 * Connect to the database.  
 * 
 *****************************/
$_mysql = mysqli_connect('localhost','db_user','db_password','osbible');


/*******************************
 * 
 * Word boundary syntax for REGEXP. READ THIS IF THE SEARCH THROWS AN ERROR.
 * 
 * MariaDB and MySQL 5.7 and earlier use the POSIX markers [[:<:]] and [[:>:]].
 * MySQL 8.0.4 replaced the regex engine with ICU, which uses \b instead and rejects
 * 	the POSIX form outright with "repetition-operator operand invalid".
 * These two variables are the switch. See script 6 for the longer explanation.
 * 
 *****************************/

$WB_START='\b';			# MariaDB / MySQL 5.7:  '[[:<:]]'
$WB_END='\b';			# MariaDB / MySQL 5.7:  '[[:>:]]'

$per_page=10;			# how many results on a page. Change it here, or let the visitor choose.
$window=9;				# how many numbered links to show at once.
						# 	An odd number keeps the current page centered. An even one
						# 	leaves the row lopsided, with more pages on one side than the other.


/*******************************
 * 
 * Which page did the visitor ask for?
 * 
 * The original wrote $currentpage=$_GET['page']-1 and cast to int afterwards. Subtracting
 * 	from a string that is not a number is a TypeError in PHP 8, so the cast has to come
 * 	first. That cast is the entire defense on this value before it reaches the LIMIT clause.
 * 
 * Page numbers here are one-based from top to bottom. The original mixed the two -- it
 * 	stored a zero-based page and then compared it against the one-based numbers in the
 * 	link loop, so no link ever received the "active" class and the current page was never
 * 	marked. That is the bug this rewrite is mostly about.
 * 
 *****************************/

if(isset($_GET['page']))
	{
	$currentpage=(int)$_GET['page'];
	}
else
	{
	$currentpage=1;
	}
if($currentpage<1){$currentpage=1;}


/*******************************
 * 
 * Build the search. This is script 7's code, with the same two corrections:
 * 	one branch instead of two, and each term escaped as it goes into its own condition.
 * 	The original ran the query through sprintf() with mysqli_real_escape_string() as the
 * 	argument, but the format string had no %s in it -- so the escaped value went nowhere
 * 	and the terms reached the query unescaped. It happens twice in this script.
 * 
 *****************************/

$keyword='love';
$_keyword=$keyword;
$SearchKey=[];							// the SQL conditions
$Terms=[];								// the plain terms, for the highlighter
$Verses=[];

if(strstr($keyword,'"'))							// Catch quotes that will designate a phrase
	{
	preg_match_all('/"(.*?)"/',$keyword,$Matches);	// This expression pulls any words within quotes into the array Matches
	foreach($Matches[1] as $kw)
		{
		$_kw=str_replace(' ','_',trim($kw));		//  We'll replace the space with an underline so we can avoid it later
		$keyword=str_replace($kw,$_kw,$keyword);	//		for example 'David "loved her"' is now 'David "loved_her"'
		}
	$keyword=str_replace('"','',$keyword);			// remove the quotes
	}

$Words=explode(' ',$keyword);
foreach($Words as $kw)
	{
	if($kw==''){continue;}
	$kw=str_replace('_',' ',$kw);					//  return any underscores to spaces.
	$Terms[]=$kw;									//  the term as it was typed, for the highlighter
	$pattern=str_replace('*','[a-zA-Z]*',$kw);		//  change any asterisks to a usable regular expression
	$SearchKey[]="`text` REGEXP '".mysqli_real_escape_string($_mysql, $WB_START.$pattern.$WB_END)."'";
	}
$search_key=implode(' AND ',$SearchKey);			// ...and linking them with 'AND' operators.


/*******************************
 * 
 * The count.
 * 
 * Because of the strong's coding in `kjvs` it's not usable for a complicated search,
 * 	so we use `kjv`, which holds the plain text.
 * This time we just want the total, so we know how many pages there are. Two queries
 * 	per page is the cost of pagination -- an unpaginated page needs only one.
 * 
 *****************************/

$count=0;
$querytext="SELECT count(`id`) AS `total` FROM `kjv` WHERE $search_key";
$query = mysqli_query($_mysql, $querytext);
if(mysqli_errno($_mysql))
	{
	echo ": " . mysqli_error($_mysql) . "\n<hr>$querytext";
	}
if($query !== false) 
	{
	$Value = mysqli_fetch_assoc($query);
	$count=(int)$Value['total'];
	}

# Since we are showing ten per page, we need to know how many pages we need.
# 	ceil() covers the leftovers: 305 results over 10 per page is 31 pages, not 30.
$pages=($count>0)?(int)ceil($count/$per_page):1;

# Now that the total is known, a page number past the end can be pulled back.
if($currentpage>$pages){$currentpage=$pages;}

$start=($currentpage-1)*$per_page;		# where the LIMIT begins -- zero-based, as MySQL wants it
$begin=$start+1;						# while we use a zero for our query, for humans it's a one
$end=min($start+$per_page,$count);		# the last page is usually short, so don't promise ten


/*******************************
 * 
 * The page itself.
 * 
 * $start is an integer by construction above, so it is safe in the LIMIT clause.
 * 
 *****************************/

$querytext="SELECT * FROM `kjv`
			WHERE	$search_key
			LIMIT	$start,$per_page;";

	# Submit the query
$query = mysqli_query($_mysql, $querytext);
	# Receive the results
if(mysqli_errno($_mysql))
	{
	# If there's an error, display it and the querytext for debugging
	echo ": " . mysqli_error($_mysql) . "\n<hr>$querytext";
	}
# If there's a result...
if($query and mysqli_num_rows($query))
	{
	# 	Begin processing the results
	while ($dbRow = mysqli_fetch_assoc($query))
		{
	# 	Get the id of this row
		$id=$dbRow['id'];
	#	create the reference
		$ref=$dbRow['book']." ".$dbRow['chapter'].":".$dbRow['verse'];
	#	get the text of the verse
		$text=$dbRow['text'];	
	#	Filter out the Strong's numbers (we don't need them now)
		$text=preg_replace('/\{(.*?)\}/', '', $text);
	#	put the text and the reference into an array.
		$Verses[$id]['ref']=$ref;	
		$Verses[$id]['text']=$text;
		}
	}


/*******************************
 * 
 * The results.
 * 
 * preg_quote() keeps a term from being read as a pattern, then the asterisk is put back
 * 	deliberately so a wildcard search highlights the whole word it matched.
 * 
 *****************************/

$result_text="\n";
foreach($Verses as $Verse)
	{
	$ref=$Verse['ref'];
	$text=$Verse['text'];
	foreach($Terms as $term)
		{
		$pattern=str_replace('\*','[a-zA-Z]*',preg_quote($term,'/'));
		$text=preg_replace("/\b$pattern\b/i", '<b style="color:#990000">$0</b>', $text);
		}
	$result_text .=  "  <div style=\"width:450px;margin-bottom:20px;margin-top:20px\">
    &ldquo;$text&rdquo;&mdash;$ref
  </div>\n";	
	}


/*******************************
 * 
 * THE PAGINATION.
 * 
 * Searching for "love" gives around 31 pages, which is far too many links to lay out in
 * 	a 450px column, so the list is truncated to a window around the current page with an
 * 	arrow at each end.
 * 
 * 	On page 1:   [-1-][2][3][4][5][6][7][8][9][>]
 * 	On page 6:   [<][2][3][4][5][-6-][7][8][9][>]
 * 	On page 31:  [<][23][24][25][26][27][28][29][30][-31-]
 * 
 * With a nine-wide window there is room for four pages either side of the current one.
 * 	The minimum that makes sense is three (previous, current, next); anything less and the
 * 	arrows are doing all the work.
 * 
 * The links are relative -- "?page=2" rather than "8.%20Pagination.php?page=2" -- so the
 * 	script still works if it is reached through a rewrite rule or renamed.
 * 
 *****************************/

function pagination($currentpage,$pages,$window)
	{
	# One page needs no links at all.
	if($pages<2){return '';}

	# Try to center the window on the current page...
	$first=$currentpage-(int)floor($window/2);
	if($first<1){$first=1;}

	# ...then pull it back if that ran past the end. Clamping $first a second time
	# 	matters when there are fewer pages than the window is wide.
	$last=$first+$window-1;
	if($last>$pages)
		{
		$last=$pages;
		$first=max(1,$last-$window+1);
		}

	$links='';
	# The back arrow only appears once there is something behind the window.
	if($first>1)
		{
		$prev=$first-1;
		$links.="    <a href=\"?page=$prev\" class=\"pagination page\">&laquo;</a>\n";
		}

	for($p=$first;$p<=$last;$p++)
		{
		# Both sides of this comparison are one-based, so the current page is
		# 	actually marked. In the original they were not.
		$active=($p==$currentpage)?' active':'';
		$links.="    <a href=\"?page=$p\" class=\"pagination page num$active\"><span>$p</span></a>\n";
		}

	# ...and the forward arrow only while there is something ahead of it.
	if($last<$pages)
		{
		$next=$last+1;
		$links.="    <a href=\"?page=$next\" class=\"pagination page\">&raquo;</a>\n";
		}

	return "  <div class=\"pagination row\">\n$links  </div>\n";
	}

$pagination=pagination($currentpage,$pages,$window);


/*******************************
 * 
 * Output. Links above the results and again below them, so a visitor who has read to the
 * 	bottom of the page does not have to scroll back up to go on.
 * 
 *****************************/

echo "<div style=\"float:left;width:450px;margin-left:30px;padding:0 15px;margin-bottom:10px;border:1px solid #000\">\n";
echo "  <div style=\"width:450px;margin-bottom:20px;\">
    <h3>$begin - $end of $count verses containing the word &ldquo;$_keyword&rdquo;</h3>
  </div>\n";
echo $pagination.$result_text.$pagination;
echo "</div>\n";

?>


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
