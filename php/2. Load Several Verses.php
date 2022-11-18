<?php 
/*************************************************************************************
 * 
 * 	This script is to demonstrate the initial database connection (assuming mysql) 
 * 		and loading of several verses.
 * 
 ***************************************************************************************/

/*******************************
 * 
 * Connect to the database. 
 * For demonstration purposes I actually used db_user and db_password for my local intranet.
 * 	You'll want to use something more secure.
 * The database is named `osbible` (Yours may be different.)
 * 
 *****************************/

  # $_mysql will be our db connection variable. Mysqli requires it for almost everything.
$_mysql = mysqli_connect('localhost','db_user','db_password','osbible');

/******************************
 * 
 * Get the list of book names from the database and put them into an array
 * We will used the array $Book (I always use capital letters to note arrays. It helps me identify them more easily within the coding.)
 * 
 *****************************/

	# The mysql query, cleaned with sprintf
$querytext = sprintf("SELECT * FROM `kjv_books`;");
	# Submit the query
$query=mysqli_query($_mysql, $querytext);
	# Check for errors
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
	# 	Let's grab the book name, too.
		$book=$dbRow['book'];
	# 	Put the entire row into an array element
		$Books[$id]=$dbRow;
	# 	An easy array to find the book id from the name.	
		$Bids[$book]=$id;					
		}
	}
	# Supposedly you're supposed to do this.
mysqli_free_result($query);

/*******************************
 * 
 * Get a selection of verses based on a simple reference.
 * 
 *****************************/

	# We will start with a few typical passage reference.
	# 	The first is the snadard two verse, comma delimited reference.
	#	The second is the dash delimited verse list.
$reference1='John 3:16,17';
$reference2='John 3:1-16';
	# Since we're assuming a correct reference, we split the reference
list($book1,$ref1)=explode(' ',$reference1);
list($book2,$ref2)=explode(' ',$reference2);
	# Using the $bids (book ids) array we can get the book id.
$bid1=$Bids[$book1];
$bid2=$Bids[$book2];
	# We split the remaining ref into chapter and verse.
list($chapter1,$verses1)=explode(':',$ref1);
list($chapter2,$verses2)=explode(':',$ref2);

/********************************************
 * 
 *  Let's start with reference #1
 * 		Since we will have two digits separated by a comma, we can fetch the two, and retienve those specific verses
 * 
 *******************************************/

list($first,$second)=explode(',',$verses1);
$querytext1=sprintf("SELECT	* 		FROM	`kjvs`
					WHERE	`book` 		= 		'%s'
					AND 	`chapter`	=		'%s'
					AND		
						(`verse`		=		'%s'
						OR `verse`		=		'%s')
					LIMIT	2;",
			mysqli_real_escape_string($_mysql, $bid1),
			mysqli_real_escape_string($_mysql, $chapter1),
			mysqli_real_escape_string($_mysql, $first),
			mysqli_real_escape_string($_mysql, $second));

/********************************************
 * 
 * Explanation of the first querytext... 
 * 	We use sprintf to clean the query text and prevent any mysql injection hacking.
 * 	We allow an OR statement to get the two verses, and limit the ressults to just 2
 * 
 * For the second query we used the two numbers as boundaries
 */

	# Submit the first query
$query = mysqli_query($_mysql, $querytext1);
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
		$id=$dbRow['verse'];
	# 	Let's grab the book name, too.
		$text1=$dbRow['text'];	
		# Filter out the Strong's numbers (we don't need them now)
		$text1=preg_replace('/\{(.*?)\}/', '', $text1);	
		$Verses1[$id]=$text1;
		}
	}
$out1='';
foreach($Verses1 as $v=>$text1)
	{
	$out1.="<p style=\"text-align:justify;margin:0\"><b>$v</b> $text1</p>";
	}
echo "<div style=\"width:400px;margin-left:50px\"><h3>Passage #1</h3>$out1<p style=\"text-align:right\">&mdash;$book1 $chapter1:$verses1</p></div>";	

/********************************************
 * 
 *  Now let's do reference #2
 * 		Since we will have the beginning and ending of a list, we simply iuse those as boundaries
 * 
 *******************************************/

list($start,$end)=explode('-',$verses2);

$querytext2=sprintf("SELECT	*	 		FROM	`kjvs`
					WHERE	`book` 		= 		'%s'
					AND 	`chapter`	=		'%s'
					AND		`verse`		>=		'%s'
					AND		`verse`		<=		'%s';",
			mysqli_real_escape_string($_mysql, $bid2),
			mysqli_real_escape_string($_mysql, $chapter2),
			mysqli_real_escape_string($_mysql, $start),
			mysqli_real_escape_string($_mysql, $end));

$query = mysqli_query($_mysql, $querytext2);
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
		$id=$dbRow['verse'];
	# 	Let's grab the book name, too.
		$text2=$dbRow['text'];	
		# Filter out the Strong's numbers (we don't need them now)
		$text2=preg_replace('/\{(.*?)\}/', '', $text2);	
		$Verses2[$id]=$text2;
		}
	}
$out2='';
foreach($Verses2 as $v=>$text2)
	{
	$out2.="<p style=\"text-align:justify;margin:0\"><b>$v</b> $text2</p>";
	}

echo "<div style=\"width:400px;margin-left:50px\"><h3>Passage #2</h3>$out2<p style=\"text-align:right\">&mdash;$book2 $chapter2:$verses2</p></div>";	



?>