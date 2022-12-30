<?php 
error_reporting(E_ALL);
echo "<h1>Pagination.</h1>\n";
/*************************************************************************************
 * 
 * 	This script is to demonstrate basic pagination to allow the site visitor to navigate through a lengthy result.
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
 * Get a selection of verses based on a simple keyword.
 * 
 *****************************/

$keyword='love';
if(isset($_GET['page']))
	{
	$currentpage=$_GET['page']-1;			# Since we'll use this variable in our mysql query, 
	$currentpage=(int)$currentpage;				#   we want to make sure it's an integer to prevent any mysql injection hack.						
	}
else
	{
	$currentpage=0;
	}
$start=$currentpage*10;
$end=$start+10;						# for now we are use ten results per page. 
									# 	This can be changed in the programming, or even by the visitor if you allow it.
$begin=$start+1;					# while we use a zero for our query, for humans it's a one


$_keyword=$keyword;$SearchKey=[];
echo "<div style=\"float:left;width:450px;margin-left:30px;padding:0 15px;margin-bottom:10px;border:1px solid #000\">\n";
$Verses=[];
	
if(strstr($keyword,'"'))							// Catch quotes that will designate a phrase
	{
	preg_match_all('/"(.*?)"/',$keyword,$Matches); 	// This expression pulls any words within quote into the array Matches
	$newKeywords=$Matches[1];						// Now we have an array with the phrases.
		
	foreach($newKeywords as $kw)
		{
		$_kw=str_replace(' ','_',trim($kw));		//  We'll replace the space with an underline so we can avoid it later
		$keyword=str_replace($kw,$_kw,$keyword);	//		for examples 'David "loved her"' is now 'David "loved_her"' 
		}
	$keyword=str_replace('"','',$keyword);			// remove the quotes
	}

if(strstr($keyword,' '))							// if we have multiple words...
	{
	$Keywords=explode(' ',$keyword);
	foreach($Keywords as $kw)
		{
		$kw=str_replace('_',' ',$kw);				//  return any underscores to spaces.
		$kw=str_replace('*','[a-zA-Z]*',$kw);		//  change any asterisks to a usable regular expression
		$SearchKey[]="`text` REGEXP '[[:<:]]".$kw."[[:>:]]'";		// We are creating an arrray of regular expressions
		}
	$search_key=implode(' AND ',$SearchKey);		// ...and linking them with 'AND' operators.
	}
else
	{
	$keyword=str_replace('_',' ',$keyword);			// phrases are now turned back into the correct form
	$keyword=str_replace('*','[a-zA-Z]*',$keyword);	// again, turn the asterisk into the regular expression
	$search_key="`text` REGEXP '[[:<:]]".$keyword."[[:>:]]'";	//	Since there were no spaces, we only have one keyword (or phrase)
	}
													//	Because of the strong's coding in 'kjvs' it's not usable for a complicated search.
													//  	so we use `kjv` which has the plain text. 
													//		Also, this time, we just want the total count
$querytext=sprintf("SELECT	count(`id`) 		FROM	`kjv`
					WHERE	$search_key",
			mysqli_real_escape_string($_mysql, $keyword));
$query = mysqli_query($_mysql, $querytext);
if(mysqli_errno($_mysql))
	{
	echo ": " . mysqli_error($_mysql) . "\n<hr>$querytext";
	}
if ($query !== false) 
	{
	$Value = mysqli_fetch_assoc($query);
	$count=$Value['count(`id`)'];
	}

													// Now, we want just the few specified by the start and the length
$querytext=sprintf("SELECT	* 		FROM	`kjv`
					WHERE	$search_key
					LIMIT	$start,10;",
			mysqli_real_escape_string($_mysql, $keyword));

	# Submit the query
$query = mysqli_query($_mysql, $querytext);
	# Receive the results
if(mysqli_errno($_mysql))
	{
	# If there's an error, display it and the querytext for debugging
	echo ": " . mysqli_error($_mysql) . "\n<hr>$querytext";
	}
# If there's a result...
if(mysqli_num_rows($query))
	{
	# 	Begin processing the results
	while ($dbRow = mysqli_fetch_assoc($query))
		{
	# 	Get the id of this row
		$id=$dbRow['id'];
	#	get the book name
		$book=$dbRow['book'];
	#	get the chapter
		$chapter=$dbRow['chapter'];
	#	get the verse
		$verse=$dbRow['verse'];
	#	create the reference
		$ref="$book $chapter:$verse";
	#	get the text of the verse
		$text=$dbRow['text'];	
	#	Filter out the Strong's numbers (we don't need them now)
		$text=preg_replace('/\{(.*?)\}/', '', $text);
	#	put the text and the reference into an array.
	#	Yes, we could output it here, but more often than not there will be other things we need to do, 
	#		or put this code in a function that will only return this array
		$Verses[$id]['ref']=$ref;	
		$Verses[$id]['text']=$text;
		}
	}

#	Now we'll out put the results. First a reminder of the keyword...
echo "  <div style=\"width:450px;margin-bottom:20px;\">
    <h3>$begin - $end of $count verses containing the word &ldquo;$_keyword&rdquo;</h3>
  </div>\n";
#	Now the list (first 10) of the verse we found.
$result_text="\n";
foreach($Verses as $Verse)
	{
	$ref=$Verse['ref'];
	$text=$Verse['text'];
	# here's we'll use preg_replace to find the keyword, make it bold and dark red.
	if(strstr($_keyword,'"') or strstr($_keyword,'*'))
		{
		foreach($newKeywords as $kw)
			{
			$_kw=str_replace(' ','_',trim($kw));		//  We'll replace the space with an underline so we can avoid it later
			$keyword=str_replace($kw,$_kw,$keyword);
			}
		$Words=explode(' ',$keyword);
		foreach($Words as $word)
			{
			$word=str_replace('_',' ',$word);
			$text=preg_replace("/\b$word\b/i", '<b style="color:#990000">$0</b>', $text);
			}
		$text=preg_replace("/\b$keyword\b/i", '<b style="color:#990000">$0</b>', $text);
		}
	else
		{
		if(strstr($keyword,' '))
			{
			list($first,$second)=explode(' ',$keyword);
			$text=preg_replace("/\b$first\b/i", '<b style="color:#990000">$0</b>', $text);
			$text=preg_replace("/\b$second\b/i", '<b style="color:#990000">$0</b>', $text);
			}
		else
			{
			$text=preg_replace("/\b$keyword\b/i", '<b style="color:#990000">$0</b>', $text);				
			}
		}
	# Now we'll echo the verse with the reference.
	$result_text .=  "  <div style=\"width:450px;margin-bottom:20px;margin-top:20px\">
    &ldquo;$text&rdquo;&mdash;$ref
  </div>\n";	
	}
$pages=floor($count/10);			# Since we are showing ten per page, we need to know how many pages we need.
if($pages*10!=$count)				# While a multiple of ten would work, if there are more...
	{
	$pages++;						#	...then we'll need another page.
	$lastpage=$count-($pages*10);	#	...and we'll need to note how many items will be on the last page;
	}
else
	{
	$lastpage=10;					# If the count IS a multiple of then, obviously, our last page count will be ten.
	}
$currentpage=$start/10;				# We are using the item count as the $start variable. We could just as easily use the page.
									# 	I tend to favor the item # because it allow the visitor to manually change the start item,
									#	rather than being stuck to a page.

									# THE PAGINATION...
									# In this case (using the keyword "love") there will be 30 pages which would be too many to show the links, 
									# 	so we'll truncate them. 
									# If we want to show 10 links (this number can be whatever fits the pagination area)
									#	then we need to display them with some sort of symbols to show that it's a partial list.
									# Typically if we're on the first page we show that, seven more, then a next arrow and a last arrow
									# 	That gives us ten spaces.i.e.[-1-][2][3][4][5][6][7][8][>][>>]
									# However, if we've moved on into the list, then we'll want to show a start arrow, and previous arrow
									# 	the three pages before our current page, the current page, the next three, a next arrow and an end arrow. 
									#  	i.e. [<<][<][3][4][5][-6-][7][8][9][>][>>]
									# This means that you need at least 5 spaces minimum to show the pagination (start,previous, current, next, last)
									#	i.e. [<<][<][-6-][>][>>] (assuming we're on page 6). Obviously, you would really want more than 5 spaces.
									# So we need to create the logic to lay out our pagination.

									# Since we can show 3 previous pages, we won't need the start and previous arrows until on page 6 or higher.
									#  i.e. [1][2][3][4][-5-][6][7][8][>][>>] vs. [<<][<][3][4][5][-6-][7][8][>][>>]
									# You'll notice than by using an even numebr of pages, we are left with a lopsides pagination.
									#	That's because the current page plus the pages on either side will need to be an odd number.
									#	If avoiding the lopsidesness is a priority to you, then use an odd number of pages.
$_pages=$pages+1;$_end=true;$end='';
$note='';
$pagination = " 
  <div class=\"pagination row\">";
$b=$currentpage-4;
$note.=__LINE__." \$b=$b, \$lastpage=$lastpage, \$count=$count, \$pages=$pages<br>\n";
if($b<1){$b=1;}
$note.=__LINE__." \$b=$b<br>\n";
if($b>1){$prev=$b-1;$begin=" <a href=\"8.%20Pagination.php?page=$prev\" class=\"pagination page\">&laquo;</a>\n";}
else{$begin='';}
$note.=__LINE__." \$b=$b<br>\n";
$e=$b+8;
$note.=__LINE__." \$b=$b, \$e=$e<br>\n";
if($e>$pages){$e=$pages;$b=$pages-8;}
$note.=__LINE__." \$b=$b, \$e=$e<br>\n";
if($e<$pages)
	{
	$next=$e+1;
	$end="    <a href=\"8.%20Pagination.php?page=$next\" class=\"pagination page\">&raquo;</a>\n";
	}

$nums='';$next=0;
$c=0;								# our page counter
$_c=$b;
$note.="Current page is $currentpage<br>
Total pages: $pages<br>\n
start at $b<br>
end at $e";
for($p=$b;$p<=$e;$p++)
	{
	$_p=$p;						# This is the display number
/************
 * if $p <$b ignore
 * if $p >=$b but less than $b+8 output
 * if $b
 * 
 */
	if($p==$currentpage)
		{
		$nums.= "    <a href=\"8.%20Pagination.php?page=$_p\" class=\"pagination page num active\"><span>$_p</span></a>\n";
		}
	else
		{
		$nums.= "    <a href=\"8.%20Pagination.php?page=$_p\" class=\"pagination page num\"><span>$_p</span></a>\n";
		}
	}
$pagination .= "
$begin$nums$end  </div>\n";

	echo $pagination.$result_text.$pagination.'</div>'."<br><div style=\"margin-left:550px\">$note</div>";



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