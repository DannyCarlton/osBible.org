-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Nov 17, 2022 at 02:23 PM
-- Server version: 10.4.24-MariaDB
-- PHP Version: 8.1.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `phpbible_av`
--

-- --------------------------------------------------------

--
-- Table structure for table `kjv_books`
--

CREATE TABLE `kjv_books` (
  `id` int(2) NOT NULL DEFAULT 0,
  `book` varchar(20) NOT NULL DEFAULT '',
  `chapters` int(3) DEFAULT NULL,
  `abbr` text DEFAULT NULL,
  `kjav_abr` varchar(3) NOT NULL,
  `longname` text NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Dumping data for table `kjv_books`
--

INSERT INTO `kjv_books` (`id`, `book`, `chapters`, `abbr`, `kjav_abr`, `longname`) VALUES
(1, 'Genesis', 50, 'Gen, Gn, Ge', 'gen', 'The First Book of Moses, called <b>Genesis</b>'),
(2, 'Exodus', 40, 'Exo, Ex, Exod', 'exo', 'The Second Book of Moses, called <b>Exodus</b>'),
(3, 'Leviticus', 27, 'Lev, Lv', 'lev', 'The Third Book of Moses, called <b>Leviticus</b>'),
(4, 'Numbers', 36, 'Num, Nm, Nu', 'num', 'The Fourth Book of Moses, called <b>Numbers</b>'),
(5, 'Deuteronomy', 34, 'Deu, Dt, Deut', 'deu', 'The Fifth Book of Moses, called <b>Deuteronomy</b>'),
(6, 'Joshua', 24, 'Jos, Josh', 'jos', 'The Book of <b>Joshua</b>'),
(7, 'Judges', 21, 'Jdg, Judg, jud', 'jdg', 'The Book of <b>Judges</b>'),
(8, 'Ruth', 4, 'Rth, Rt, Rut, Ru', 'rut', 'The Book of <b>Ruth</b>'),
(9, '1 Samuel', 31, '1 Sam, 1 Sa, 1 S, 1 Sm, 1sa, 1Sam, sa1', '1sa', 'The <b>First Book of Samuel</b> Otherwise Called The First Book of the Kings'),
(10, '2 Samuel', 24, '2 Sam, 2 Sa, 2 S, 2 Sm, 2sa, 2Sam, sa2', '2sa', 'The <b>Second Book of Samuel</b> Otherwise Called The Second Book of the Kings'),
(11, '1 Kings', 22, '1 Ki, 1 Kgs, 1ki, 1 king, kg1', '1ki', 'The <b>First Book of the Kings,</b> Commonly Called the Third Book of the Kings'),
(12, '2 Kings', 25, '2 Ki, 2 Kgs, 2ki, 2 king, kg2', '2ki', 'The <b>Second Book of the Kings,</b> Commonly Called the Fourth Book of the Kings'),
(13, '1 Chronicles', 29, '1 Chr, 1 Ch, 1 Chron, 1ch, 1Chr, ch1', '1ch', 'The <b>First Book of the Chronicles</b>'),
(14, '2 Chronicles', 36, '2 Chr, 2 Ch, 2 Chron, 2ch, 2Chr, ch2', '2ch', 'The <b>Second Book of the Chronicles</b>'),
(15, 'Ezra', 10, 'Ezr, Esr, 1 Ezr', 'ezr', '<b>Ezra</b>'),
(16, 'Nehemiah', 13, 'Neh, Ne, 2 Ezr', 'neh', 'The Book of <b>Nehemiah</b>'),
(17, 'Esther', 10, 'Est, Esth, es', 'est', 'The Book of <b>Esther</b>'),
(18, 'Job', 42, 'Job, Jb', 'job', 'The Book of <b>Job</b>'),
(19, 'Psalms', 150, 'Psa, Ps, Psalm', 'psa', 'The Book of <b>Psalms</b>'),
(20, 'Proverbs', 31, 'Pro, Pr, Prov, Prv', 'pro', 'The <b>Proverbs</b>'),
(21, 'Ecclesiastes', 12, 'Ecc, Ec, Eccl', 'ecc', '<b>Ecclesiastes</b> or, the Preacher'),
(22, 'Song of Solomon', 8, 'Sng, Song, Canticles, Cant, so, Solomon, Song of Solomon, SS', 'sos', 'The <b>Song of Solomon</b>'),
(23, 'Isaiah', 66, 'Isa, Is', 'isa', 'The Book of the Prophet <b>Isaiah</b>'),
(24, 'Jeremiah', 52, 'Jer, Jr', 'jer', 'The Book of the Prophet <b>Jeremiah</b>'),
(25, 'Lamentations', 5, 'Lam, La, Lm', 'lam', 'The <b>Lamentations</b> of Jeremiah'),
(26, 'Ezekiel', 48, 'Ezk, Ez, Ezek, Eze', 'eze', 'The Book of the Prophet <b>Ezekiel</b>'),
(27, 'Daniel', 12, 'Dan, Da, Dn', 'dan', 'The Book of <b>Daniel</b>'),
(28, 'Hosea', 14, 'Hos, Ho', 'hos', '<b>Hosea</b>'),
(29, 'Joel', 3, 'Jol, Jl, joe', 'joe', '<b>Joel</b>'),
(30, 'Amos', 9, 'Amo, Am', 'amo', '<b>Amos</b>'),
(31, 'Obadiah', 1, 'Oba, Ob, Obad, Obd', 'oba', '<b>Obadiah</b>'),
(32, 'Jonah', 4, 'Jon, Jnh', 'jon', '<b>Jonah</b>'),
(33, 'Micah', 7, 'Mic, Mi, Mch', 'mic', '<b>Micah</b>'),
(34, 'Nahum', 3, 'Nah, Na, Nam', 'nah', '<b>Nahum</b>'),
(35, 'Habakkuk', 3, 'Hab, Ha', 'hab', '<b>Habakkuk</b>'),
(36, 'Zephaniah', 3, 'Zeph, Zep', 'zep', '<b>Zephaniah</b>'),
(37, 'Haggai', 2, 'Hag, Hagg', 'hag', '<b>Haggai</b>'),
(38, 'Zechariah', 14, 'Zech, Zec, Zch, zac', 'zec', '<b>Zechariah</b>'),
(39, 'Malachi', 4, 'Mal, Ml', 'mal', '<b>Malachi</b>'),
(40, 'Matthew', 28, 'Mt, Mat, Matt', 'mat', 'The Gospel According to <b>St. Matthew</b>'),
(41, 'Mark', 16, 'Mk, Mr, Mrk', 'mar', 'The Gospel According to <b>St. Mark</b>'),
(42, 'Luke', 24, 'Lk, Luk, L, Lu', 'luk', 'The Gospel According to <b>St. Luke</b>'),
(43, 'John', 21, 'Jn, Jhn, J, Joh', 'joh', 'The Gospel According to <b>St. John</b>'),
(44, 'Acts', 28, 'Act, Ac', 'act', 'The <b>Acts</b> of the Apostles'),
(45, 'Romans', 16, 'Rom, Ro, R, Rm', 'rom', 'The Epistle of Paul the Apostle to the <b>Romans</b>'),
(46, '1 Corinthians', 16, '1 Cor, 1 Co, 1 c, 1co, 1cor, co1', '1co', 'The First Epistle of Paul the Apostle to the <b>Corinthians</b>'),
(47, '2 Corinthians', 13, '2 Cor, 2 Co, 2 c, 2co, 2cor, co2', '2co', 'The Second Epistle of Paul the Apostle to the <b>Corinthians</b>'),
(48, 'Galatians', 6, 'Gal, Ga, G', 'gal', 'The Epistle of Paul the Apostle to the <b>Galatians</b>'),
(49, 'Ephesians', 6, 'Eph, Ep, E', 'eph', 'The Epistle of Paul the Apostle to the <b>Ephesians</b>'),
(50, 'Philippians', 4, 'Phil, Php, Ph, Phili', 'phi', 'The Epistle of Paul the Apostle to the <b>Philippians</b>'),
(51, 'Colossians', 4, 'Col', 'col', 'The Epistle of Paul the Apostle to the <b>Colossians</b>'),
(52, '1 Thessalonians', 5, '1 Th, 1 Thess, 1 Thes, 1th, th1', '1th', 'The First Epistle of Paul the Apostle to the <b>Thessalonians</b>'),
(53, '2 Thessalonians', 3, '2 Th, 2 Thess, 2 Thes, 2th, th2', '2th', 'The Second Epistle of Paul the Apostle to the <b>Thessalonians</b>'),
(54, '1 Timothy', 6, '1 Tim, 1 Ti, 1 T, 1 Tm, 1ti, ti1', '1ti', 'The First Epistle of Paul the Apostle to <b>Timothy</b>'),
(55, '2 Timothy', 4, '2 Tim, 2 Ti, 2 T, 2 Tm, 2ti, ti2', '2ti', 'The Second Epistle of Paul the Apostle to <b>Timothy</b>'),
(56, 'Titus', 3, 'Tit, Tt', 'tts', 'The Epistle of Paul the Apostle to <b>Titus</b>'),
(57, 'Philemon', 1, 'Phm, Phlm, Philem, Phile, plm', 'phm', 'The Epistle of Paul the Apostle to <b>Philemon</b>'),
(58, 'Hebrews', 13, 'Heb, Hebr, H, Hbr, Hebrews', 'heb', 'The Epistle of Paul the Apostle to the <b>Hebrews</b>'),
(59, 'James', 5, 'Jas, Jam, Ja', 'jas', 'The General Epistle of <b>James</b>'),
(60, '1 Peter', 5, '1 Pet, 1 Pt, 1 P, 1 Pe, 1pe, 1Pet, pe1', '1pe', 'The First Epistle General of <b>Peter</b>'),
(61, '2 Peter', 3, '2 Pet, 2 Pt, 2 P, 2 Pe, 2pe, 2Pet, pe2', '2pe', 'The Second Epistle General of <b>Peter</b>'),
(62, '1 John', 5, '1 Jn, 1 Jo, 1 J, 1jo, 1 joh, jo1', '1jn', 'The First Epistle General of <b>John</b>'),
(63, '2 John', 1, '2 Jn, 2 Jo, 2 J, 2jo, 2 joh, jo2', '2jn', 'The Second Epistle of <b>John</b>'),
(64, '3 John', 1, '3 Jn, 3 Jo, 3 J, 3jo, 3 joh, jo3', '3jn', 'The Third Epistle of <b>John</b>'),
(65, 'Jude', 1, 'Jude, Jd, jde', 'jde', 'The General Epistle of <b>Jude</b>'),
(66, 'Revelation', 22, 'Rev, revelations, rv', 'rev', 'The <b>Revelation</b> of St. John the Divine');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `kjv_books`
--
ALTER TABLE `kjv_books`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ref` (`book`,`chapters`),
  ADD KEY `book` (`book`,`abbr`(7),`kjav_abr`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
