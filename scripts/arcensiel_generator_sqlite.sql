-- Database: arcensiel
-- ------------------------------------------------------

--
-- Table structure for table `articles`
--

DROP TABLE IF EXISTS `articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `articles` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `slug` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(100) NOT NULL,
  `published_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `summary` text,
  `content` mediumtext NOT NULL,
  `type` int DEFAULT '0',
  `status` varchar(255) NOT NULL DEFAULT 'published',
  UNIQUE(`slug`),
  FOREIGN KEY (`type`) REFERENCES types(`id`) ON DELETE SET NULL
);

--
-- Table structure for table `articles_covers`
--

DROP TABLE IF EXISTS `articles_covers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `articles_covers` (
  `file_id` int NOT NULL,
  `article_id` int NOT NULL,
  UNIQUE(`article_id`),
  FOREIGN KEY (`file_id`) REFERENCES files(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`article_id`) REFERENCES articles(`id`) ON DELETE CASCADE
);

--
-- Table structure for table `articles_tags`
--

DROP TABLE IF EXISTS `articles_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `articles_tags` (
  `article_id` int NOT NULL,
  `tag_id` int NOT NULL,
  PRIMARY KEY (`article_id`,`tag_id`),
  FOREIGN KEY (`article_id`) REFERENCES articles(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tag_id`) REFERENCES tags(`id`) ON DELETE CASCADE
);

--
-- Table structure for table `bookmarks`
--

DROP TABLE IF EXISTS `bookmarks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookmarks` (
  `user_id` int NOT NULL,
  `article_id` int NOT NULL,
  `added_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`article_id`),
  FOREIGN KEY (`user_id`) REFERENCES users(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`article_id`) REFERENCES articles(`id`) ON DELETE CASCADE
);

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `slug` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `sort_order` int DEFAULT NULL,
  `section_id` int NOT NULL,
  `removable` tinyint(1) DEFAULT NULL,
  UNIQUE(`slug`),
  FOREIGN KEY (`section_id`) REFERENCES sections(`id`) ON DELETE CASCADE
);

--
-- Table structure for table `events`
--

DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `events` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  UNIQUE(`slug`)
);

--
-- Table structure for table `files`
--

DROP TABLE IF EXISTS `files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `files` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `url` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text,
  `size` bigint NOT NULL,
  `type` varchar(255) DEFAULT NULL,
  `status` tinyint(1) DEFAULT NULL,
  `uploader` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(`url`)
);

--
-- Table structure for table `files_categories`
--

DROP TABLE IF EXISTS `files_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `files_categories` (
  `file_id` int NOT NULL,
  `category_id` int NOT NULL,
  `sort_order` int DEFAULT NULL,
  PRIMARY KEY (`file_id`,`category_id`),
  UNIQUE(`category_id`,`sort_order`),
  FOREIGN KEY (`file_id`) REFERENCES files(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES categories(`id`) ON DELETE CASCADE
);

--
-- Table structure for table `logs`
--

DROP TABLE IF EXISTS `logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `logs` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `time` datetime DEFAULT CURRENT_TIMESTAMP,
  `ip` varchar(255) DEFAULT NULL,
  `localization` varchar(255) DEFAULT NULL,
  `section` varchar(255) DEFAULT NULL,
  `message` varchar(255) DEFAULT NULL,
  `content` text,
  `user` varchar(255) DEFAULT NULL
);

--
-- Table structure for table `sections`
--

DROP TABLE IF EXISTS `sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sections` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `slug` varchar(255) DEFAULT NULL,
  `path` varchar(255) DEFAULT NULL,
  UNIQUE(`slug`)
);

--
-- Table structure for table `tags`
--

DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tags` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  UNIQUE(`slug`)
);

--
-- Table structure for table `tokens`
--

DROP TABLE IF EXISTS `tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tokens` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `user` varchar(255) DEFAULT NULL,
  `token` varchar(255) NOT NULL,
  `expiry` bigint NOT NULL DEFAULT '0',
  `valid` tinyint(1) DEFAULT '1',
  UNIQUE(`token`)
);

--
-- Table structure for table `types`
--

DROP TABLE IF EXISTS `types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `types` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `slug` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `color` varchar(255) DEFAULT '#aaaaaa',
  UNIQUE(`slug`)
);

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `username` varchar(100) COLLATE NOCASE NOT NULL,
  `auth_type` CHECK(auth_type IN ('CAS','local')) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `role` CHECK(role IN ('user','admin')) DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `email` varchar(255) COLLATE NOCASE NOT NULL DEFAULT NULL,
  UNIQUE(`auth_type`,`username`),
  UNIQUE(`email`)
);

