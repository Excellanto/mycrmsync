-- MariaDB dump 10.18  Distrib 10.4.17-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: mysimconnect
-- ------------------------------------------------------
-- Server version	10.4.17-MariaDB-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `user_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `subject_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_logs_user_id_foreign` (`user_id`),
  KEY `activity_logs_created_at_index` (`created_at`),
  KEY `activity_logs_module_action_index` (`module`,`action`),
  KEY `activity_logs_module_index` (`module`),
  KEY `activity_logs_action_index` (`action`),
  KEY `activity_logs_tenant_id_foreign` (`tenant_id`),
  KEY `activity_logs_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  CONSTRAINT `activity_logs_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `activity_logs_chk_1` CHECK (json_valid(`properties`))
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,1,1,'Administrator','settings','created','Created settings: #10','{\"new\":{\"key\":\"email_ingestion.initial_sync_limit\",\"value\":\"100\",\"type\":\"integer\",\"updated_at\":\"2026-05-09 18:11:36\",\"created_at\":\"2026-05-09 18:11:36\",\"id\":10}}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 18:11:36','2026-05-09 18:11:36','App\\Models\\SiteSetting',10),(2,NULL,NULL,NULL,'permissions','created','Created permissions: nav.user-management.permissions.show','{\"new\":{\"guard_name\":\"web\",\"name\":\"nav.user-management.permissions.show\",\"updated_at\":\"2026-05-09 19:03:20\",\"created_at\":\"2026-05-09 19:03:20\",\"id\":229}}','127.0.0.1','Symfony','2026-05-09 19:03:20','2026-05-09 19:03:20','Spatie\\Permission\\Models\\Permission',229),(3,NULL,NULL,NULL,'permissions','created','Created permissions: nav.settings.pool_allocation.show','{\"new\":{\"guard_name\":\"web\",\"name\":\"nav.settings.pool_allocation.show\",\"updated_at\":\"2026-05-09 19:03:20\",\"created_at\":\"2026-05-09 19:03:20\",\"id\":230}}','127.0.0.1','Symfony','2026-05-09 19:03:20','2026-05-09 19:03:20','Spatie\\Permission\\Models\\Permission',230),(4,NULL,NULL,NULL,'permissions','created','Created permissions: nav.settings.data-configuration.show','{\"new\":{\"guard_name\":\"web\",\"name\":\"nav.settings.data-configuration.show\",\"updated_at\":\"2026-05-09 19:03:20\",\"created_at\":\"2026-05-09 19:03:20\",\"id\":231}}','127.0.0.1','Symfony','2026-05-09 19:03:20','2026-05-09 19:03:20','Spatie\\Permission\\Models\\Permission',231),(5,NULL,NULL,NULL,'permissions','created','Created permissions: nav.settings.system-settings.show','{\"new\":{\"guard_name\":\"web\",\"name\":\"nav.settings.system-settings.show\",\"updated_at\":\"2026-05-09 19:03:20\",\"created_at\":\"2026-05-09 19:03:20\",\"id\":232}}','127.0.0.1','Symfony','2026-05-09 19:03:20','2026-05-09 19:03:20','Spatie\\Permission\\Models\\Permission',232),(6,1,1,'Administrator','users','updated','Updated users: First Tenant','{\"old\":{\"name\":\"Administrator\",\"updated_at\":\"2025-12-08T12:02:16.000000Z\"},\"new\":{\"name\":\"First Tenant\",\"updated_at\":\"2026-05-09 19:07:49\"},\"changes\":{\"name\":\"First Tenant\",\"updated_at\":\"2026-05-09 19:07:49\"}}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 19:07:49','2026-05-09 19:07:49','App\\Models\\User',1),(7,1,1,'First Tenant','users','updated','Updated users: First Tenant','{\"old\":{\"remember_token\":\"********\"},\"new\":{\"remember_token\":\"********\"},\"changes\":{\"remember_token\":\"********\"}}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 19:10:27','2026-05-09 19:10:27','App\\Models\\User',1),(8,1,1,'First Tenant','auth','logout','User logged out','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 19:10:27','2026-05-09 19:10:27','App\\Models\\User',1),(9,1,1,'First Tenant','auth','logout','User logged out','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 19:10:27','2026-05-09 19:10:27','App\\Models\\User',1),(10,3,NULL,'Master Admin','auth','login','User logged in','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 19:10:32','2026-05-09 19:10:32','App\\Models\\User',3),(11,3,NULL,'Master Admin','auth','login','User logged in','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 19:10:32','2026-05-09 19:10:32','App\\Models\\User',3),(12,3,NULL,'Master Admin','auth','logout','User logged out','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 19:45:11','2026-05-09 19:45:11','App\\Models\\User',3),(13,3,NULL,'Master Admin','auth','logout','User logged out','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 19:45:11','2026-05-09 19:45:11','App\\Models\\User',3),(14,1,1,'First Tenant','auth','login','User logged in','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 19:45:22','2026-05-09 19:45:22','App\\Models\\User',1),(15,1,1,'First Tenant','auth','login','User logged in','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 19:45:22','2026-05-09 19:45:22','App\\Models\\User',1),(16,NULL,NULL,NULL,'permissions','created','Created permissions: nav.settings.system-settings.show','{\"new\":{\"guard_name\":\"web\",\"name\":\"nav.settings.system-settings.show\",\"updated_at\":\"2026-05-09 19:47:24\",\"created_at\":\"2026-05-09 19:47:24\",\"id\":233}}','127.0.0.1','Symfony','2026-05-09 19:47:24','2026-05-09 19:47:24','Spatie\\Permission\\Models\\Permission',233),(17,1,1,'First Tenant','users','updated','Updated users: First Tenant','{\"old\":{\"remember_token\":\"********\"},\"new\":{\"remember_token\":\"********\"},\"changes\":{\"remember_token\":\"********\"}}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 19:51:04','2026-05-09 19:51:04','App\\Models\\User',1),(18,1,1,'First Tenant','auth','logout','User logged out','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 19:51:04','2026-05-09 19:51:04','App\\Models\\User',1),(19,1,1,'First Tenant','auth','logout','User logged out','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 19:51:04','2026-05-09 19:51:04','App\\Models\\User',1),(20,1,1,'First Tenant','auth','login','User logged in','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 19:51:17','2026-05-09 19:51:17','App\\Models\\User',1),(21,1,1,'First Tenant','auth','login','User logged in','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 19:51:17','2026-05-09 19:51:17','App\\Models\\User',1),(22,1,1,'First Tenant','users','updated','Updated users: First Tenant','{\"old\":{\"remember_token\":\"********\"},\"new\":{\"remember_token\":\"********\"},\"changes\":{\"remember_token\":\"********\"}}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 19:57:15','2026-05-09 19:57:15','App\\Models\\User',1),(23,1,1,'First Tenant','auth','logout','User logged out','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 19:57:15','2026-05-09 19:57:15','App\\Models\\User',1),(24,1,1,'First Tenant','auth','logout','User logged out','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 19:57:15','2026-05-09 19:57:15','App\\Models\\User',1),(25,1,1,'First Tenant','auth','login','User logged in','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 19:57:25','2026-05-09 19:57:25','App\\Models\\User',1),(26,1,1,'First Tenant','auth','login','User logged in','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 19:57:25','2026-05-09 19:57:25','App\\Models\\User',1),(27,1,1,'First Tenant','users','updated','Updated users: First Tenant','{\"old\":{\"remember_token\":\"********\"},\"new\":{\"remember_token\":\"********\"},\"changes\":{\"remember_token\":\"********\"}}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 20:22:01','2026-05-09 20:22:01','App\\Models\\User',1),(28,1,1,'First Tenant','auth','logout','User logged out','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 20:22:01','2026-05-09 20:22:01','App\\Models\\User',1),(29,1,1,'First Tenant','auth','logout','User logged out','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 20:22:01','2026-05-09 20:22:01','App\\Models\\User',1),(30,NULL,NULL,NULL,'auth','failed_login','Failed login attempt','{\"email\":\"master@admin.com\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 20:22:10','2026-05-09 20:22:10',NULL,NULL),(31,NULL,NULL,NULL,'auth','failed_login','Failed login attempt','{\"email\":\"master@admin.com\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 20:22:10','2026-05-09 20:22:10',NULL,NULL),(32,3,NULL,'Master Admin','auth','login','User logged in','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 20:22:32','2026-05-09 20:22:32','App\\Models\\User',3),(33,3,NULL,'Master Admin','auth','login','User logged in','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-09 20:22:32','2026-05-09 20:22:32','App\\Models\\User',3),(34,1,1,'First Tenant','auth','login','User logged in','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-10 01:17:38','2026-05-10 01:17:38','App\\Models\\User',1),(35,1,1,'First Tenant','auth','login','User logged in','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-10 01:17:38','2026-05-10 01:17:38','App\\Models\\User',1),(36,1,1,'First Tenant','users','updated','Updated users: First Tenant','{\"old\":{\"remember_token\":\"********\"},\"new\":{\"remember_token\":\"********\"},\"changes\":{\"remember_token\":\"********\"}}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-10 01:18:12','2026-05-10 01:18:12','App\\Models\\User',1),(37,1,1,'First Tenant','auth','logout','User logged out','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-10 01:18:12','2026-05-10 01:18:12','App\\Models\\User',1),(38,1,1,'First Tenant','auth','logout','User logged out','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-10 01:18:12','2026-05-10 01:18:12','App\\Models\\User',1),(39,3,NULL,'Master Admin','auth','login','User logged in','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-10 01:18:31','2026-05-10 01:18:31','App\\Models\\User',3),(40,3,NULL,'Master Admin','auth','login','User logged in','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-10 01:18:31','2026-05-10 01:18:31','App\\Models\\User',3),(41,3,NULL,'Master Admin','auth','login','User logged in','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-10 09:02:03','2026-05-10 09:02:03','App\\Models\\User',3),(42,3,NULL,'Master Admin','auth','login','User logged in','[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-05-10 09:02:03','2026-05-10 09:02:03','App\\Models\\User',3);
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('laravel_cache_spatie.permission.cache','a:3:{s:5:\"alias\";a:6:{s:1:\"a\";s:2:\"id\";s:1:\"b\";s:4:\"name\";s:1:\"c\";s:10:\"guard_name\";s:1:\"r\";s:5:\"roles\";s:1:\"j\";s:4:\"slug\";s:1:\"k\";s:17:\"is_platform_scope\";}s:11:\"permissions\";a:211:{i:0;a:4:{s:1:\"a\";i:1;s:1:\"b\";s:18:\"admin-panel-access\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:1;a:4:{s:1:\"a\";i:2;s:1:\"b\";s:10:\"users.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:2;a:4:{s:1:\"a\";i:3;s:1:\"b\";s:12:\"users.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:3;a:4:{s:1:\"a\";i:4;s:1:\"b\";s:12:\"users.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:4;a:4:{s:1:\"a\";i:5;s:1:\"b\";s:12:\"users.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:5;a:4:{s:1:\"a\";i:6;s:1:\"b\";s:10:\"roles.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:6;a:4:{s:1:\"a\";i:7;s:1:\"b\";s:12:\"roles.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:7;a:4:{s:1:\"a\";i:8;s:1:\"b\";s:12:\"roles.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:8;a:4:{s:1:\"a\";i:9;s:1:\"b\";s:12:\"roles.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:9;a:4:{s:1:\"a\";i:10;s:1:\"b\";s:16:\"permissions.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:10;a:4:{s:1:\"a\";i:11;s:1:\"b\";s:18:\"permissions.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:11;a:4:{s:1:\"a\";i:12;s:1:\"b\";s:18:\"permissions.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:12;a:4:{s:1:\"a\";i:13;s:1:\"b\";s:18:\"permissions.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:13;a:4:{s:1:\"a\";i:14;s:1:\"b\";s:13:\"settings.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:14;a:4:{s:1:\"a\";i:15;s:1:\"b\";s:15:\"settings.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:15;a:4:{s:1:\"a\";i:16;s:1:\"b\";s:14:\"languages.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:16;a:4:{s:1:\"a\";i:17;s:1:\"b\";s:16:\"languages.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:17;a:4:{s:1:\"a\";i:18;s:1:\"b\";s:14:\"languages.sync\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:18;a:4:{s:1:\"a\";i:19;s:1:\"b\";s:18:\"activity-logs.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:19;a:4:{s:1:\"a\";i:20;s:1:\"b\";s:20:\"activity-logs.export\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:20;a:4:{s:1:\"a\";i:21;s:1:\"b\";s:20:\"activity-logs.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:21;a:4:{s:1:\"a\";i:22;s:1:\"b\";s:12:\"clients.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:22;a:4:{s:1:\"a\";i:23;s:1:\"b\";s:14:\"clients.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:23;a:4:{s:1:\"a\";i:24;s:1:\"b\";s:14:\"clients.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:24;a:4:{s:1:\"a\";i:25;s:1:\"b\";s:14:\"clients.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:25;a:4:{s:1:\"a\";i:26;s:1:\"b\";s:11:\"buyers.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:26;a:4:{s:1:\"a\";i:27;s:1:\"b\";s:13:\"buyers.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:27;a:4:{s:1:\"a\";i:28;s:1:\"b\";s:13:\"buyers.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:28;a:4:{s:1:\"a\";i:29;s:1:\"b\";s:13:\"buyers.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:29;a:4:{s:1:\"a\";i:30;s:1:\"b\";s:31:\"user-management.show-in-sidebar\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:30;a:4:{s:1:\"a\";i:31;s:1:\"b\";s:39:\"client-buyer-management.show-in-sidebar\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:31;a:4:{s:1:\"a\";i:32;s:1:\"b\";s:18:\"colour-object.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:32;a:4:{s:1:\"a\";i:33;s:1:\"b\";s:20:\"colour-object.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:33;a:4:{s:1:\"a\";i:34;s:1:\"b\";s:20:\"colour-object.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:34;a:4:{s:1:\"a\";i:35;s:1:\"b\";s:20:\"colour-object.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:35;a:4:{s:1:\"a\";i:36;s:1:\"b\";s:12:\"colours.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:36;a:4:{s:1:\"a\";i:37;s:1:\"b\";s:14:\"colours.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:37;a:4:{s:1:\"a\";i:38;s:1:\"b\";s:14:\"colours.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:38;a:4:{s:1:\"a\";i:39;s:1:\"b\";s:14:\"colours.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:39;a:4:{s:1:\"a\";i:40;s:1:\"b\";s:12:\"leather.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:40;a:4:{s:1:\"a\";i:41;s:1:\"b\";s:14:\"leather.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:41;a:4:{s:1:\"a\";i:42;s:1:\"b\";s:14:\"leather.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:42;a:4:{s:1:\"a\";i:43;s:1:\"b\";s:14:\"leather.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:43;a:4:{s:1:\"a\";i:44;s:1:\"b\";s:11:\"lining.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:44;a:4:{s:1:\"a\";i:45;s:1:\"b\";s:13:\"lining.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:45;a:4:{s:1:\"a\";i:46;s:1:\"b\";s:13:\"lining.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:46;a:4:{s:1:\"a\";i:47;s:1:\"b\";s:13:\"lining.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:47;a:4:{s:1:\"a\";i:48;s:1:\"b\";s:14:\"loop-band.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:48;a:4:{s:1:\"a\";i:49;s:1:\"b\";s:16:\"loop-band.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:49;a:4:{s:1:\"a\";i:50;s:1:\"b\";s:16:\"loop-band.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:50;a:4:{s:1:\"a\";i:51;s:1:\"b\";s:16:\"loop-band.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:51;a:4:{s:1:\"a\";i:52;s:1:\"b\";s:11:\"buckle.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:52;a:4:{s:1:\"a\";i:53;s:1:\"b\";s:13:\"buckle.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:53;a:4:{s:1:\"a\";i:54;s:1:\"b\";s:13:\"buckle.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:54;a:4:{s:1:\"a\";i:55;s:1:\"b\";s:13:\"buckle.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:55;a:4:{s:1:\"a\";i:56;s:1:\"b\";s:17:\"buckle-width.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:56;a:4:{s:1:\"a\";i:57;s:1:\"b\";s:19:\"buckle-width.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:57;a:4:{s:1:\"a\";i:58;s:1:\"b\";s:19:\"buckle-width.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:58;a:4:{s:1:\"a\";i:59;s:1:\"b\";s:19:\"buckle-width.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:59;a:4:{s:1:\"a\";i:60;s:1:\"b\";s:16:\"thread-size.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:60;a:4:{s:1:\"a\";i:61;s:1:\"b\";s:18:\"thread-size.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:61;a:4:{s:1:\"a\";i:62;s:1:\"b\";s:18:\"thread-size.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:62;a:4:{s:1:\"a\";i:63;s:1:\"b\";s:18:\"thread-size.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:63;a:4:{s:1:\"a\";i:64;s:1:\"b\";s:14:\"end-width.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:64;a:4:{s:1:\"a\";i:65;s:1:\"b\";s:16:\"end-width.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:65;a:4:{s:1:\"a\";i:66;s:1:\"b\";s:16:\"end-width.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:66;a:4:{s:1:\"a\";i:67;s:1:\"b\";s:16:\"end-width.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:67;a:4:{s:1:\"a\";i:72;s:1:\"b\";s:14:\"reinforce.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:68;a:4:{s:1:\"a\";i:73;s:1:\"b\";s:16:\"reinforce.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:69;a:4:{s:1:\"a\";i:74;s:1:\"b\";s:16:\"reinforce.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:70;a:4:{s:1:\"a\";i:75;s:1:\"b\";s:16:\"reinforce.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:71;a:4:{s:1:\"a\";i:80;s:1:\"b\";s:18:\"stamping-long.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:72;a:4:{s:1:\"a\";i:81;s:1:\"b\";s:20:\"stamping-long.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:73;a:4:{s:1:\"a\";i:82;s:1:\"b\";s:20:\"stamping-long.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:74;a:4:{s:1:\"a\";i:83;s:1:\"b\";s:20:\"stamping-long.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:75;a:4:{s:1:\"a\";i:84;s:1:\"b\";s:19:\"stamping-short.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:76;a:4:{s:1:\"a\";i:85;s:1:\"b\";s:21:\"stamping-short.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:77;a:4:{s:1:\"a\";i:86;s:1:\"b\";s:21:\"stamping-short.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:78;a:4:{s:1:\"a\";i:87;s:1:\"b\";s:21:\"stamping-short.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:79;a:4:{s:1:\"a\";i:88;s:1:\"b\";s:19:\"strap-pictures.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:80;a:4:{s:1:\"a\";i:89;s:1:\"b\";s:21:\"strap-pictures.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:81;a:4:{s:1:\"a\";i:90;s:1:\"b\";s:21:\"strap-pictures.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:82;a:4:{s:1:\"a\";i:91;s:1:\"b\";s:21:\"strap-pictures.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:83;a:4:{s:1:\"a\";i:92;s:1:\"b\";s:12:\"special.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:84;a:4:{s:1:\"a\";i:93;s:1:\"b\";s:14:\"special.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:85;a:4:{s:1:\"a\";i:94;s:1:\"b\";s:14:\"special.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:86;a:4:{s:1:\"a\";i:95;s:1:\"b\";s:14:\"special.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:87;a:4:{s:1:\"a\";i:96;s:1:\"b\";s:16:\"prod-design.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:88;a:4:{s:1:\"a\";i:97;s:1:\"b\";s:18:\"prod-design.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:89;a:4:{s:1:\"a\";i:98;s:1:\"b\";s:18:\"prod-design.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:90;a:4:{s:1:\"a\";i:99;s:1:\"b\";s:18:\"prod-design.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:91;a:4:{s:1:\"a\";i:104;s:1:\"b\";s:42:\"production-data-management.show-in-sidebar\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:92;a:4:{s:1:\"a\";i:105;s:1:\"b\";s:22:\"app-configuration.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:93;a:4:{s:1:\"a\";i:106;s:1:\"b\";s:24:\"app-configuration.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:94;a:4:{s:1:\"a\";i:107;s:1:\"b\";s:48:\"template-construction-management.show-in-sidebar\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:95;a:4:{s:1:\"a\";i:108;s:1:\"b\";s:11:\"mlcode.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:96;a:4:{s:1:\"a\";i:109;s:1:\"b\";s:13:\"mlcode.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:97;a:4:{s:1:\"a\";i:110;s:1:\"b\";s:13:\"mlcode.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:98;a:4:{s:1:\"a\";i:111;s:1:\"b\";s:13:\"mlcode.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:99;a:4:{s:1:\"a\";i:112;s:1:\"b\";s:10:\"sizes.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:100;a:4:{s:1:\"a\";i:113;s:1:\"b\";s:12:\"sizes.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:101;a:4:{s:1:\"a\";i:114;s:1:\"b\";s:12:\"sizes.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:102;a:4:{s:1:\"a\";i:115;s:1:\"b\";s:12:\"sizes.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:103;a:4:{s:1:\"a\";i:116;s:1:\"b\";s:12:\"varnish.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:104;a:4:{s:1:\"a\";i:117;s:1:\"b\";s:14:\"varnish.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:105;a:4:{s:1:\"a\";i:118;s:1:\"b\";s:14:\"varnish.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:106;a:4:{s:1:\"a\";i:119;s:1:\"b\";s:14:\"varnish.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:107;a:4:{s:1:\"a\";i:120;s:1:\"b\";s:16:\"design-name.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:108;a:4:{s:1:\"a\";i:121;s:1:\"b\";s:18:\"design-name.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:109;a:4:{s:1:\"a\";i:122;s:1:\"b\";s:18:\"design-name.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:110;a:4:{s:1:\"a\";i:123;s:1:\"b\";s:18:\"design-name.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:111;a:4:{s:1:\"a\";i:124;s:1:\"b\";s:23:\"design-description.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:112;a:4:{s:1:\"a\";i:125;s:1:\"b\";s:25:\"design-description.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:113;a:4:{s:1:\"a\";i:126;s:1:\"b\";s:25:\"design-description.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:114;a:4:{s:1:\"a\";i:127;s:1:\"b\";s:25:\"design-description.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:115;a:4:{s:1:\"a\";i:128;s:1:\"b\";s:20:\"note-info-line1.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:116;a:4:{s:1:\"a\";i:129;s:1:\"b\";s:22:\"note-info-line1.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:117;a:4:{s:1:\"a\";i:130;s:1:\"b\";s:22:\"note-info-line1.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:118;a:4:{s:1:\"a\";i:131;s:1:\"b\";s:22:\"note-info-line1.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:119;a:4:{s:1:\"a\";i:132;s:1:\"b\";s:20:\"note-info-line2.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:120;a:4:{s:1:\"a\";i:133;s:1:\"b\";s:22:\"note-info-line2.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:121;a:4:{s:1:\"a\";i:134;s:1:\"b\";s:22:\"note-info-line2.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:122;a:4:{s:1:\"a\";i:135;s:1:\"b\";s:22:\"note-info-line2.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:123;a:4:{s:1:\"a\";i:136;s:1:\"b\";s:27:\"strap-length-long-part.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:124;a:4:{s:1:\"a\";i:137;s:1:\"b\";s:29:\"strap-length-long-part.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:125;a:4:{s:1:\"a\";i:138;s:1:\"b\";s:29:\"strap-length-long-part.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:126;a:4:{s:1:\"a\";i:139;s:1:\"b\";s:29:\"strap-length-long-part.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:127;a:4:{s:1:\"a\";i:140;s:1:\"b\";s:28:\"strap-length-short-part.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:128;a:4:{s:1:\"a\";i:141;s:1:\"b\";s:30:\"strap-length-short-part.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:129;a:4:{s:1:\"a\";i:142;s:1:\"b\";s:30:\"strap-length-short-part.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:130;a:4:{s:1:\"a\";i:143;s:1:\"b\";s:30:\"strap-length-short-part.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:131;a:4:{s:1:\"a\";i:144;s:1:\"b\";s:10:\"tools.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:132;a:4:{s:1:\"a\";i:145;s:1:\"b\";s:12:\"tools.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:133;a:4:{s:1:\"a\";i:146;s:1:\"b\";s:12:\"tools.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:134;a:4:{s:1:\"a\";i:147;s:1:\"b\";s:12:\"tools.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:135;a:4:{s:1:\"a\";i:148;s:1:\"b\";s:26:\"user-management.users.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:136;a:4:{s:1:\"a\";i:149;s:1:\"b\";s:28:\"user-management.users.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:137;a:4:{s:1:\"a\";i:150;s:1:\"b\";s:28:\"user-management.users.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:138;a:4:{s:1:\"a\";i:151;s:1:\"b\";s:28:\"user-management.users.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:139;a:4:{s:1:\"a\";i:152;s:1:\"b\";s:26:\"user-management.roles.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:140;a:4:{s:1:\"a\";i:153;s:1:\"b\";s:28:\"user-management.roles.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:141;a:4:{s:1:\"a\";i:154;s:1:\"b\";s:28:\"user-management.roles.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:142;a:4:{s:1:\"a\";i:155;s:1:\"b\";s:28:\"user-management.roles.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:143;a:4:{s:1:\"a\";i:156;s:1:\"b\";s:32:\"user-management.permissions.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:144;a:4:{s:1:\"a\";i:157;s:1:\"b\";s:34:\"user-management.permissions.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:145;a:4:{s:1:\"a\";i:158;s:1:\"b\";s:34:\"user-management.permissions.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:146;a:4:{s:1:\"a\";i:159;s:1:\"b\";s:34:\"user-management.permissions.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:147;a:4:{s:1:\"a\";i:160;s:1:\"b\";s:34:\"user-management.activity-logs.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:148;a:4:{s:1:\"a\";i:161;s:1:\"b\";s:36:\"user-management.activity-logs.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:149;a:4:{s:1:\"a\";i:162;s:1:\"b\";s:36:\"user-management.activity-logs.export\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:150;a:4:{s:1:\"a\";i:163;s:1:\"b\";s:30:\"member-management.members.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:151;a:4:{s:1:\"a\";i:164;s:1:\"b\";s:32:\"member-management.members.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:152;a:4:{s:1:\"a\";i:165;s:1:\"b\";s:32:\"member-management.members.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:153;a:4:{s:1:\"a\";i:166;s:1:\"b\";s:32:\"member-management.members.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:154;a:4:{s:1:\"a\";i:167;s:1:\"b\";s:32:\"member-management.members.export\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:155;a:4:{s:1:\"a\";i:168;s:1:\"b\";s:30:\"give-ask-management.gives.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:156;a:4:{s:1:\"a\";i:169;s:1:\"b\";s:32:\"give-ask-management.gives.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:157;a:4:{s:1:\"a\";i:170;s:1:\"b\";s:32:\"give-ask-management.gives.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:158;a:4:{s:1:\"a\";i:171;s:1:\"b\";s:32:\"give-ask-management.gives.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:159;a:4:{s:1:\"a\";i:172;s:1:\"b\";s:32:\"give-ask-management.gives.export\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:160;a:4:{s:1:\"a\";i:173;s:1:\"b\";s:29:\"give-ask-management.asks.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:161;a:4:{s:1:\"a\";i:174;s:1:\"b\";s:31:\"give-ask-management.asks.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:162;a:4:{s:1:\"a\";i:175;s:1:\"b\";s:31:\"give-ask-management.asks.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:163;a:4:{s:1:\"a\";i:176;s:1:\"b\";s:31:\"give-ask-management.asks.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:164;a:4:{s:1:\"a\";i:177;s:1:\"b\";s:31:\"give-ask-management.asks.export\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:165;a:4:{s:1:\"a\";i:178;s:1:\"b\";s:31:\"member-management.chapters.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:166;a:4:{s:1:\"a\";i:179;s:1:\"b\";s:33:\"member-management.chapters.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:167;a:4:{s:1:\"a\";i:180;s:1:\"b\";s:33:\"member-management.chapters.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:168;a:4:{s:1:\"a\";i:181;s:1:\"b\";s:33:\"member-management.chapters.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:169;a:4:{s:1:\"a\";i:182;s:1:\"b\";s:33:\"member-management.chapters.export\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:170;a:4:{s:1:\"a\";i:183;s:1:\"b\";s:19:\"job-postings.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:171;a:4:{s:1:\"a\";i:184;s:1:\"b\";s:19:\"job-postings.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:172;a:4:{s:1:\"a\";i:185;s:1:\"b\";s:19:\"job-postings.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:173;a:4:{s:1:\"a\";i:186;s:1:\"b\";s:25:\"nav.settings.pricing.show\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:174;a:4:{s:1:\"a\";i:187;s:1:\"b\";s:33:\"nav.settings.pool_allocation.show\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:175;a:4:{s:1:\"a\";i:188;s:1:\"b\";s:12:\"tenants.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:176;a:4:{s:1:\"a\";i:189;s:1:\"b\";s:14:\"tenants.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:177;a:4:{s:1:\"a\";i:190;s:1:\"b\";s:32:\"nav.user-management.tenants.show\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:178;a:4:{s:1:\"a\";i:191;s:1:\"b\";s:17:\"job-postings.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:179;a:4:{s:1:\"a\";i:192;s:1:\"b\";s:21:\"job-applications.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:180;a:4:{s:1:\"a\";i:193;s:1:\"b\";s:23:\"job-applications.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:181;a:4:{s:1:\"a\";i:194;s:1:\"b\";s:23:\"job-applications.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:182;a:4:{s:1:\"a\";i:195;s:1:\"b\";s:23:\"job-applications.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:183;a:4:{s:1:\"a\";i:196;s:1:\"b\";s:23:\"nav.configurations.show\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:184;a:4:{s:1:\"a\";i:197;s:1:\"b\";s:40:\"nav.configurations.email-management.show\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:185;a:4:{s:1:\"a\";i:198;s:1:\"b\";s:19:\"email-accounts.view\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:186;a:4:{s:1:\"a\";i:199;s:1:\"b\";s:21:\"email-accounts.create\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:187;a:4:{s:1:\"a\";i:200;s:1:\"b\";s:21:\"email-accounts.update\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:188;a:4:{s:1:\"a\";i:201;s:1:\"b\";s:21:\"email-accounts.delete\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:189;a:4:{s:1:\"a\";i:202;s:1:\"b\";s:19:\"email-accounts.sync\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:11;i:1;i:12;i:2;i:13;}}i:190;a:4:{s:1:\"a\";i:203;s:1:\"b\";s:23:\"nav.job-management.show\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:12;}}i:191;a:4:{s:1:\"a\";i:204;s:1:\"b\";s:37:\"nav.job-management.job-vacancies.show\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:12;}}i:192;a:4:{s:1:\"a\";i:205;s:1:\"b\";s:29:\"nav.candidate-management.show\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:12;}}i:193;a:4:{s:1:\"a\";i:206;s:1:\"b\";s:44:\"nav.candidate-management.upload-resumes.show\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:12;}}i:194;a:4:{s:1:\"a\";i:207;s:1:\"b\";s:44:\"nav.candidate-management.candidate-data.show\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:12;}}i:195;a:4:{s:1:\"a\";i:208;s:1:\"b\";s:24:\"nav.user-management.show\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:12;}}i:196;a:4:{s:1:\"a\";i:209;s:1:\"b\";s:30:\"nav.user-management.users.show\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:12;}}i:197;a:4:{s:1:\"a\";i:210;s:1:\"b\";s:30:\"nav.user-management.roles.show\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:12;}}i:198;a:4:{s:1:\"a\";i:211;s:1:\"b\";s:38:\"nav.user-management.activity-logs.show\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:12;}}i:199;a:4:{s:1:\"a\";i:212;s:1:\"b\";s:17:\"nav.settings.show\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:12;}}i:200;a:4:{s:1:\"a\";i:213;s:1:\"b\";s:27:\"nav.settings.languages.show\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:12;}}i:201;a:4:{s:1:\"a\";i:214;s:1:\"b\";s:29:\"nav.settings.ai-settings.show\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:12;}}i:202;a:4:{s:1:\"a\";i:215;s:1:\"b\";s:17:\"nav.platform.show\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:12;}}i:203;a:4:{s:1:\"a\";i:216;s:1:\"b\";s:25:\"nav.platform.tenants.show\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:12;}}i:204;a:4:{s:1:\"a\";i:217;s:1:\"b\";s:36:\"nav.user-management.permissions.show\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:12;}}i:205;a:4:{s:1:\"a\";i:218;s:1:\"b\";s:36:\"nav.settings.data-configuration.show\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:12;}}i:206;a:4:{s:1:\"a\";i:219;s:1:\"b\";s:33:\"nav.settings.system-settings.show\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:12;}}i:207;a:3:{s:1:\"a\";i:220;s:1:\"b\";s:15:\"candidates.view\";s:1:\"c\";s:3:\"web\";}i:208;a:3:{s:1:\"a\";i:221;s:1:\"b\";s:17:\"candidates.create\";s:1:\"c\";s:3:\"web\";}i:209;a:3:{s:1:\"a\";i:222;s:1:\"b\";s:17:\"candidates.update\";s:1:\"c\";s:3:\"web\";}i:210;a:3:{s:1:\"a\";i:223;s:1:\"b\";s:17:\"candidates.delete\";s:1:\"c\";s:3:\"web\";}}s:5:\"roles\";a:3:{i:0;a:5:{s:1:\"a\";i:11;s:1:\"b\";s:12:\"Tenant Admin\";s:1:\"c\";s:3:\"web\";s:1:\"j\";s:12:\"tenant_admin\";s:1:\"k\";i:0;}i:1;a:5:{s:1:\"a\";i:12;s:1:\"b\";s:11:\"Super Admin\";s:1:\"c\";s:3:\"web\";s:1:\"j\";s:11:\"super_admin\";s:1:\"k\";i:1;}i:2;a:5:{s:1:\"a\";i:13;s:1:\"b\";s:11:\"Tenant User\";s:1:\"c\";s:3:\"web\";s:1:\"j\";s:11:\"tenant_user\";s:1:\"k\";i:0;}}}',1778428238);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `language_strings`
--

DROP TABLE IF EXISTS `language_strings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `language_strings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lang` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `language_strings_lang_file_key_unique` (`lang`,`file`,`key`),
  KEY `language_strings_lang_index` (`lang`),
  KEY `language_strings_file_index` (`file`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `language_strings`
--

LOCK TABLES `language_strings` WRITE;
/*!40000 ALTER TABLE `language_strings` DISABLE KEYS */;
/*!40000 ALTER TABLE `language_strings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_permissions`
--

LOCK TABLES `model_has_permissions` WRITE;
/*!40000 ALTER TABLE `model_has_permissions` DISABLE KEYS */;
INSERT INTO `model_has_permissions` VALUES (1,'App\\Models\\User',2);
/*!40000 ALTER TABLE `model_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_roles`
--

DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_roles`
--

LOCK TABLES `model_has_roles` WRITE;
/*!40000 ALTER TABLE `model_has_roles` DISABLE KEYS */;
INSERT INTO `model_has_roles` VALUES (11,'App\\Models\\User',1),(11,'App\\Models\\User',2),(12,'App\\Models\\User',3);
/*!40000 ALTER TABLE `model_has_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=234 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'admin-panel-access','web','2025-11-27 17:32:39','2025-11-27 17:32:39'),(2,'users.view','web','2025-11-27 17:32:39','2025-11-27 17:32:39'),(3,'users.create','web','2025-11-27 17:32:39','2025-11-27 17:32:39'),(4,'users.update','web','2025-11-27 17:32:39','2025-11-27 17:32:39'),(5,'users.delete','web','2025-11-27 17:32:39','2025-11-27 17:32:39'),(6,'roles.view','web','2025-11-27 17:32:39','2025-11-27 17:32:39'),(7,'roles.create','web','2025-11-27 17:32:39','2025-11-27 17:32:39'),(8,'roles.update','web','2025-11-27 17:32:39','2025-11-27 17:32:39'),(9,'roles.delete','web','2025-11-27 17:32:39','2025-11-27 17:32:39'),(10,'permissions.view','web','2025-11-27 17:32:39','2025-11-27 17:32:39'),(11,'permissions.create','web','2025-11-27 17:32:39','2025-11-27 17:32:39'),(12,'permissions.update','web','2025-11-27 17:32:39','2025-11-27 17:32:39'),(13,'permissions.delete','web','2025-11-27 17:32:39','2025-11-27 17:32:39'),(14,'settings.view','web','2025-11-27 17:32:39','2025-11-27 17:32:39'),(15,'settings.update','web','2025-11-27 17:32:39','2025-11-27 17:32:39'),(16,'languages.view','web','2025-11-27 17:32:39','2025-11-27 17:32:39'),(17,'languages.update','web','2025-11-27 17:32:39','2025-11-27 17:32:39'),(18,'languages.sync','web','2025-11-27 17:32:39','2025-11-27 17:32:39'),(19,'activity-logs.view','web','2025-12-03 07:49:37','2025-12-03 07:49:37'),(20,'activity-logs.export','web','2025-12-03 07:49:37','2025-12-03 07:49:37'),(21,'activity-logs.delete','web','2025-12-03 07:49:37','2025-12-03 07:49:37'),(30,'user-management.show-in-sidebar','web','2025-12-06 15:22:58','2025-12-06 15:22:58'),(148,'user-management.users.view','web','2026-02-03 03:21:16','2026-02-03 03:21:16'),(149,'user-management.users.create','web','2026-02-03 03:21:16','2026-02-03 03:21:16'),(150,'user-management.users.update','web','2026-02-03 03:21:16','2026-02-03 03:21:16'),(151,'user-management.users.delete','web','2026-02-03 03:21:16','2026-02-03 03:21:16'),(152,'user-management.roles.view','web','2026-02-03 03:21:16','2026-02-03 03:21:16'),(153,'user-management.roles.create','web','2026-02-03 03:21:16','2026-02-03 03:21:16'),(154,'user-management.roles.update','web','2026-02-03 03:21:16','2026-02-03 03:21:16'),(155,'user-management.roles.delete','web','2026-02-03 03:21:16','2026-02-03 03:21:16'),(156,'user-management.permissions.view','web','2026-02-03 03:21:16','2026-02-03 03:21:16'),(157,'user-management.permissions.create','web','2026-02-03 03:21:16','2026-02-03 03:21:16'),(158,'user-management.permissions.update','web','2026-02-03 03:21:16','2026-02-03 03:21:16'),(159,'user-management.permissions.delete','web','2026-02-03 03:21:16','2026-02-03 03:21:16'),(160,'user-management.activity-logs.view','web','2026-02-03 03:21:16','2026-02-03 03:21:16'),(161,'user-management.activity-logs.delete','web','2026-02-03 03:21:16','2026-02-03 03:21:16'),(162,'user-management.activity-logs.export','web','2026-02-03 03:21:16','2026-02-03 03:21:16'),(188,'tenants.view','web','2026-05-09 15:03:37','2026-05-09 15:03:37'),(189,'tenants.update','web','2026-05-09 15:03:37','2026-05-09 15:03:37'),(190,'nav.user-management.tenants.show','web','2026-05-09 15:03:37','2026-05-09 15:03:37'),(208,'nav.user-management.show','web','2026-05-09 15:03:40','2026-05-09 15:03:40'),(209,'nav.user-management.users.show','web','2026-05-09 15:03:40','2026-05-09 15:03:40'),(210,'nav.user-management.roles.show','web','2026-05-09 15:03:40','2026-05-09 15:03:40'),(211,'nav.user-management.activity-logs.show','web','2026-05-09 15:03:40','2026-05-09 15:03:40'),(212,'nav.settings.show','web','2026-05-09 15:03:40','2026-05-09 15:03:40'),(213,'nav.settings.languages.show','web','2026-05-09 15:03:40','2026-05-09 15:03:40'),(215,'nav.platform.show','web','2026-05-09 15:03:40','2026-05-09 15:03:40'),(216,'nav.platform.tenants.show','web','2026-05-09 15:03:40','2026-05-09 15:03:40'),(224,'dashboard.view','web','2026-05-09 16:19:52','2026-05-09 16:19:52'),(229,'nav.user-management.permissions.show','web','2026-05-09 19:03:20','2026-05-09 19:03:20'),(233,'nav.settings.system-settings.show','web','2026-05-09 19:47:24','2026-05-09 19:47:24');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `phone_otps`
--

DROP TABLE IF EXISTS `phone_otps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `phone_otps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `phone` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `otp` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `phone_otps_phone_index` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `phone_otps`
--

LOCK TABLES `phone_otps` WRITE;
/*!40000 ALTER TABLE `phone_otps` DISABLE KEYS */;
/*!40000 ALTER TABLE `phone_otps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_has_permissions`
--

DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_has_permissions`
--

LOCK TABLES `role_has_permissions` WRITE;
/*!40000 ALTER TABLE `role_has_permissions` DISABLE KEYS */;
INSERT INTO `role_has_permissions` VALUES (1,11),(1,12),(1,13),(2,11),(2,12),(2,13),(3,11),(3,12),(3,13),(4,11),(4,12),(4,13),(5,11),(5,12),(5,13),(6,12),(6,13),(7,12),(7,13),(8,12),(8,13),(9,12),(9,13),(10,12),(10,13),(11,12),(11,13),(12,12),(12,13),(13,12),(13,13),(14,11),(14,12),(14,13),(15,11),(15,12),(15,13),(16,11),(16,12),(16,13),(17,11),(17,12),(17,13),(18,11),(18,12),(18,13),(19,11),(19,12),(19,13),(20,11),(20,12),(20,13),(21,11),(21,12),(21,13),(22,13),(23,13),(24,13),(25,13),(26,13),(27,13),(28,13),(29,13),(30,11),(30,12),(30,13),(32,13),(33,13),(34,13),(35,13),(36,13),(37,13),(38,13),(39,13),(40,13),(41,13),(42,13),(43,13),(44,13),(45,13),(46,13),(47,13),(48,13),(49,13),(50,13),(51,13),(52,13),(53,13),(54,13),(55,13),(56,13),(57,13),(58,13),(59,13),(60,13),(61,13),(62,13),(63,13),(64,13),(65,13),(66,13),(67,13),(72,13),(73,13),(74,13),(75,13),(80,13),(81,13),(82,13),(83,13),(84,13),(85,13),(86,13),(87,13),(88,13),(89,13),(90,13),(91,13),(92,13),(93,13),(94,13),(95,13),(96,13),(97,13),(98,13),(99,13),(107,13),(108,13),(109,13),(110,13),(111,13),(112,13),(113,13),(114,13),(115,13),(116,13),(117,13),(118,13),(119,13),(120,13),(121,13),(122,13),(123,13),(124,13),(125,13),(126,13),(127,13),(128,13),(129,13),(130,13),(131,13),(132,13),(133,13),(134,13),(135,13),(136,13),(137,13),(138,13),(139,13),(140,13),(141,13),(142,13),(143,13),(144,13),(145,13),(146,13),(147,13),(148,11),(148,12),(148,13),(149,11),(149,12),(149,13),(150,11),(150,12),(150,13),(151,11),(151,12),(151,13),(152,11),(152,12),(152,13),(153,11),(153,12),(153,13),(154,11),(154,12),(154,13),(155,11),(155,12),(155,13),(156,11),(156,12),(156,13),(157,11),(157,12),(157,13),(158,11),(158,12),(158,13),(159,11),(159,12),(159,13),(160,11),(160,12),(160,13),(161,11),(161,12),(161,13),(162,11),(162,12),(162,13),(163,13),(164,13),(165,13),(166,13),(167,13),(168,13),(169,13),(170,13),(171,13),(172,13),(173,13),(174,13),(175,13),(176,13),(177,13),(178,13),(179,13),(180,13),(181,13),(182,13),(188,12),(188,13),(189,12),(189,13),(190,12),(190,13),(208,11),(208,12),(209,11),(209,12),(210,12),(211,11),(211,12),(212,12),(213,12),(215,12),(216,12),(224,11),(224,12),(229,12);
/*!40000 ALTER TABLE `role_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_platform_scope` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`),
  UNIQUE KEY `roles_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (11,'Tenant Admin','web','tenant_admin',0,'2026-05-09 15:03:33','2026-05-09 15:03:37'),(12,'Super Admin','web','super_admin',1,'2026-05-09 15:03:37','2026-05-09 15:03:37'),(13,'Tenant User','web','tenant_user',0,'2026-05-09 15:03:37','2026-05-09 15:03:37');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('1JBxH6FYM7HKmlGeXRfWszhij9jyYIXcusG90pMF',3,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','YTo1OntzOjY6Il90b2tlbiI7czo0MDoiaDRHWVBNVHhtTmsyaUlsWGdVSFI4bjVnTXl3cU1oZXVwaks5SFJCSSI7czozOiJ1cmwiO2E6MDp7fXM6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjI3OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvbG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aTozO30=',1778403723),('7BA7Oa81FmeiEQYAVbZIGpNtSOso9BrlsSy8N2aE',3,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiUHg1enlVNjE3enZEVU53Qjc3eGk4NW5hbW1wUzEyM2hsSXV4MzYxRCI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6Mzt9',1778375924);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `site_settings`
--

DROP TABLE IF EXISTS `site_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `site_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'string',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `site_settings_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_settings`
--

LOCK TABLES `site_settings` WRITE;
/*!40000 ALTER TABLE `site_settings` DISABLE KEYS */;
INSERT INTO `site_settings` VALUES (1,'site.name','Sibrama Admin','string','2025-11-27 17:32:39','2025-11-27 17:32:39'),(2,'site.maintenance','0','boolean','2025-11-27 17:32:39','2025-11-27 17:32:39'),(3,'site.meta','{\"description\":\"Admin Panel\"}','json','2025-11-27 17:32:39','2025-11-27 17:32:39');
/*!40000 ALTER TABLE `site_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenants`
--

DROP TABLE IF EXISTS `tenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Business',
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pan_card` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gst_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_logo_path` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `email_ingestion_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenants_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenants`
--

LOCK TABLES `tenants` WRITE;
/*!40000 ALTER TABLE `tenants` DISABLE KEYS */;
INSERT INTO `tenants` VALUES (1,'Master','Business','master@example.com',NULL,NULL,NULL,'active',0,'2026-05-09 15:03:33','2026-05-09 15:03:33');
/*!40000 ALTER TABLE `tenants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `users_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,1,'First Tenant','admin@example.com','2025-11-27 17:32:39','$2y$12$l7waFW2k9S95XLQtsq0be.FBAQauMOgBS6zuKw4gmJE1WGS0KjzvG','tNTCV4DXEcqX5gMkqFq88JzTJt4Y3eHCwXSL31PszaXOkB3arwoh104XsxYC',NULL,'2025-11-27 17:32:39','2026-05-09 19:07:49'),(2,1,'Sanjay Jha','sanjay@yahoo.com',NULL,'$2y$12$5TTL1XI5G1zyo4cYlki14u3TmdLlMfIkBzbn7daFb4BvbDzrU6KE6',NULL,NULL,'2025-11-28 14:38:36','2026-02-03 03:32:56'),(3,NULL,'Master Admin','master@example.com','2026-05-09 15:03:33','$2y$12$wlv0h.BKmxBhUb4UNBRPLOiNg5BWivXKNE6ZJvyokBdreHVYoVK7e',NULL,NULL,'2026-05-09 15:03:33','2026-05-09 15:03:33');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'mysimconnect'
--

--
-- Dumping routines for database 'mysimconnect'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-10 16:12:27
