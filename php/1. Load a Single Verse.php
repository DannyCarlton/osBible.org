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
 * Get a single verse based on a simple reference.
 * 
 *****************************/

	# We will start with a simple reference that won't need correcting,
	# 	but will need the book name converted to the id of the book
$reference='John 3:16';
	# Since we're assuming a correct reference, we split the reference
list($book,$ref)=explode(' ',$reference);
	# Using the $bids (book ids) array we can get the book id.
$bid=$Bids[$book];
	# We split the remaining ref into chapter and verse.
list($chapter,$verse)=explode(':',$ref);
/********************************************
 * 
 * Explanation of the querytext... 
 * 	We use sprintf to clean the query text and prevent any mysql injection hacking.
 * 	We limit it to one so we don't have to run through the entire table after we've found the data we need.
 */
$querytext=sprintf("SELECT	`text` 		FROM	`kjvs`
					WHERE	`book` 		= 		'%s'
					AND 	`chapter`	=		'%s'
					AND		`verse`		=		'%s'
					LIMIT	1;",
			mysqli_real_escape_string($_mysql, $bid),
			mysqli_real_escape_string($_mysql, $chapter),
			mysqli_real_escape_string($_mysql, $verse));

	# Submit the query
$query = mysqli_query($_mysql, $querytext);
	# Receive the results
$Verse = mysqli_fetch_array($query);
	# Assign the data to $text
$text=$Verse['text'];
	# Filter out the Strong's numbers (we don't need them now)
$text=preg_replace('/\{(.*?)\}/', '', $text);
	# Echo the verse
echo "&ldquo;$text&rdquo;&mdash;$book $chapter:$verse";	

/**********************************************************
 *  The page output should look like... 
 * 	“For God so loved the world, that he gave his only begotten Son, that whosoever believeth in him should not perish, but have everlasting life.”—John 3:16
 * 
 *********************************************************/


?>