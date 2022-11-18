<?php 
/*************************************************************************************
 * 
 * 	This script is to demonstrate the initial database connection (assuming mysql) 
 * 		and loading of a single verse.
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

$_mysql = mysqli_connect('localhost','db_user','db_password','osbible');

/******************************
 * 
 * Get the list of book names from the database and put them into an array
 * We will used the array $Book (I always use capital letters to note arrays. It helps me identify them more easily within the coding.)
 * 
 *****************************/

$querytext = sprintf("SELECT * FROM `kjv_books`;");						# The mysql query, cleaned with sprintf
$query=mysqli_query($_mysql, $querytext);								# submit the query
if(mysqli_errno($_mysql))												# check for errors
	{
	echo ": " . mysqli_error($_mysql) . "\n<hr>$querytext";				# if there's an error, display it and the querytext for debugging
	}
if(mysqli_num_rows($query))												# if there's a result...
	{
	while ($dbRow = mysqli_fetch_assoc($query)) 						# 	begin processing the results
		{
		$id=$dbRow['id'];												# 	get the id of this row
		$book=$dbRow['book'];											# 	let's grab the book name, too.
		$Books[$id]=$dbRow;												# 	put the entire row into an array element
		$Bids[$book]=$id;												# 	an easy array to find the book id from the name.						
		}
	}
mysqli_free_result($query);												# supposedly you're supposed to do this.

/*******************************
 * 
 * Get a single verse based on a simple reference.
 * 
 *****************************/

$reference='John 3:16';													# We will start with a simple reference that won't need correcting,
																		# 	but will need the book name converted to the id of the book
list($book,$ref)=explode(' ',$reference);								# Since we're assuming a correct reference, we split the reference
$bid=$Bids[$book];														# Using the $bids (book ids) array we can get the book id.
list($chapter,$verse)=explode(':',$ref);								# we split the remaining ref into chapter and verse.

$querytext=sprintf("SELECT	`text` 		FROM	`kjvs`
					WHERE	`book` 		= 		'%s'
					AND 	`chapter`	=		'%s'
					AND		`verse`		=		'%s'
					LIMIT	1;",
			mysqli_real_escape_string($_mysql, $bid),
			mysqli_real_escape_string($_mysql, $chapter),
			mysqli_real_escape_string($_mysql, $verse));
/********************************************
 * 
 * Explanation of the querytext... 
 * 	We use sprintf to clean the query text and prevent any mysql injection hacking.
 * 	We limit it to one so we don't have to run through the entire table after we've found the data we need.
 */

$query = mysqli_query($_mysql, $querytext);								# Submit the query
$Verse = mysqli_fetch_array($query);									# Receive the results
$text=$Verse['text'];													# Assign the data to $text
$text=preg_replace('/\{(.*?)\}/', '', $text);							# Filter out the Strong's numbers (we don't need them now)
echo "&ldquo;$text&rdquo;&mdash;$book $chapter:$verse";					# echo the verse

/**********************************************************
 *  The page output should look like... 
 * 	“For God so loved the world, that he gave his only begotten Son, that whosoever believeth in him should not perish, but have everlasting life.”—John 3:16
 * 
 *********************************************************/


?>