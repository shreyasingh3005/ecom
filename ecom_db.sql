-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: ecom_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

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
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(50) DEFAULT 'super_admin',
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` VALUES (1,'Web','Administrator','admin@gmail.com','$2y$10$/6wmbt7UjNyrAFEdV3Yz5OQSsm4xmyvQmriKcvpzmJiKFkEt.zk8i','super_admin','2026-10-03 13:37:37','2026-09-19 17:54:33','2026-10-03 13:37:37');
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` varchar(50) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,'LOGIN','admin','1','Admin logged in','127.0.0.1','2026-09-19 17:56:50'),(2,1,'LOGIN','admin','1','Admin logged in','127.0.0.1','2026-09-19 18:23:36'),(3,1,'LOGIN','admin','1','Admin logged in','127.0.0.1','2026-09-19 18:24:04'),(4,1,'LOGIN','admin','1','Admin logged in','127.0.0.1','2026-09-19 18:40:28'),(5,1,'LOGIN','admin','1','Admin logged in','127.0.0.1','2026-09-19 18:41:49'),(6,1,'LOGIN','admin','1','Admin logged in','::1','2026-09-19 18:43:31'),(7,1,'LOGIN','admin','1','Admin logged in','127.0.0.1','2026-09-19 18:50:55'),(8,1,'LOGIN','admin','1','Admin logged in','::1','2026-09-19 18:53:12'),(9,1,'LOGIN','admin','1','Admin logged in','::1','2026-10-03 11:43:30'),(10,1,'LOGIN','admin','1','Admin logged in','127.0.0.1','2026-10-03 12:12:09'),(11,1,'LOGIN','admin','1','Admin logged in','127.0.0.1','2026-10-03 12:23:37'),(12,1,'LOGIN','admin','1','Admin logged in','127.0.0.1','2026-10-03 12:35:14'),(13,1,'LOGIN','admin','1','Admin logged in','127.0.0.1','2026-10-03 12:36:47'),(14,1,'LOGIN','admin','1','Admin logged in','127.0.0.1','2026-10-03 12:37:58'),(15,1,'LOGIN','admin','1','Admin logged in','127.0.0.1','2026-10-03 13:37:37');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Oils & Tinctures','oils-tinctures',NULL,'Active','2026-09-19 17:54:33'),(2,'Edibles & Wellness','edibles-wellness',NULL,'Active','2026-09-19 17:54:33'),(3,'Topicals','topicals',NULL,'Active','2026-09-19 17:54:33'),(4,'Pet Care','pet-care',NULL,'Active','2026-09-19 17:54:33');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coupons`
--

DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `discount_type` enum('percentage','fixed') DEFAULT 'percentage',
  `discount_value` decimal(10,2) NOT NULL,
  `min_order_amount` decimal(10,2) DEFAULT 0.00,
  `max_discount_amount` decimal(10,2) DEFAULT NULL,
  `usage_limit` int(11) DEFAULT 100,
  `used_count` int(11) DEFAULT 0,
  `expiry_date` date DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coupons`
--

LOCK TABLES `coupons` WRITE;
/*!40000 ALTER TABLE `coupons` DISABLE KEYS */;
/*!40000 ALTER TABLE `coupons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_logs`
--

DROP TABLE IF EXISTS `email_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient` varchar(191) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `status` enum('sent','failed') DEFAULT 'sent',
  `error_message` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_logs`
--

LOCK TABLES `email_logs` WRITE;
/*!40000 ALTER TABLE `email_logs` DISABLE KEYS */;
INSERT INTO `email_logs` VALUES (1,'test.recipient@example.com','E2E Automated Test Email 1789822417','failed','PHP mail() returned false (SMTP credentials unconfigured).','2026-09-19 18:23:39'),(2,'test.recipient@example.com','E2E Automated Test Email 1789822444','failed','PHP mail() returned false (SMTP credentials unconfigured).','2026-09-19 18:24:06'),(3,'test.recipient@example.com','E2E Automated Test Email 1789823428','failed','PHP mail() returned false (SMTP credentials unconfigured).','2026-09-19 18:40:30'),(4,'test.recipient@example.com','E2E Automated Test Email 1789824055','failed','PHP mail() returned false (SMTP credentials unconfigured).','2026-09-19 18:50:57'),(5,'AMAN@GMAIL.COM','Your Verification Code - KAMS HEMP','failed','PHP mail() returned false (SMTP credentials unconfigured).','2026-09-19 18:54:51'),(6,'test.recipient@example.com','E2E Automated Test Email 1791009729','failed','PHP mail() returned false (SMTP credentials unconfigured).','2026-10-03 12:12:11'),(7,'test.recipient@example.com','E2E Automated Test Email 1791010417','failed','PHP mail() returned false (SMTP credentials unconfigured).','2026-10-03 12:23:39'),(8,'test.recipient@example.com','E2E Automated Test Email 1791011114','failed','PHP mail() returned false (SMTP credentials unconfigured).','2026-10-03 12:35:16'),(9,'rahul.test@example.com','Order Confirmed: #ORD-20261003-525474 - KAMS HEMP','failed','PHP mail() returned false (SMTP credentials unconfigured).','2026-10-03 12:35:41'),(10,'priya.test@example.com','Order Confirmed: #ORD-20261003-275463 - KAMS HEMP','failed','PHP mail() returned false (SMTP credentials unconfigured).','2026-10-03 12:35:43'),(11,'rahul.test@example.com','Order Confirmed: #ORD-20261003-156250 - KAMS HEMP','failed','PHP mail() returned false (SMTP credentials unconfigured).','2026-10-03 12:36:35'),(12,'priya.test@example.com','Order Confirmed: #ORD-20261003-391513 - KAMS HEMP','failed','PHP mail() returned false (SMTP credentials unconfigured).','2026-10-03 12:36:37'),(13,'test.recipient@example.com','E2E Automated Test Email 1791011207','failed','PHP mail() returned false (SMTP credentials unconfigured).','2026-10-03 12:36:49'),(14,'rahul.test@example.com','Order Confirmed: #ORD-20261003-430160 - KAMS HEMP','failed','PHP mail() returned false (SMTP credentials unconfigured).','2026-10-03 12:37:56'),(15,'priya.test@example.com','Order Confirmed: #ORD-20261003-928317 - KAMS HEMP','failed','PHP mail() returned false (SMTP credentials unconfigured).','2026-10-03 12:37:58'),(16,'test.recipient@example.com','E2E Automated Test Email 1791011278','failed','PHP mail() returned false (SMTP credentials unconfigured).','2026-10-03 12:38:00'),(17,'rahul.test@example.com','Order Confirmed: #ORD-20261003-174306 - KAMS HEMP','failed','PHP mail() returned false (SMTP credentials unconfigured).','2026-10-03 13:36:57'),(18,'priya.test@example.com','Order Confirmed: #ORD-20261003-361969 - KAMS HEMP','failed','PHP mail() returned false (SMTP credentials unconfigured).','2026-10-03 13:36:59'),(19,'test.recipient@example.com','E2E Automated Test Email 1791014857','failed','PHP mail() returned false (SMTP credentials unconfigured).','2026-10-03 13:37:39');
/*!40000 ALTER TABLE `email_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_transactions`
--

DROP TABLE IF EXISTS `inventory_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `change_qty` int(11) NOT NULL,
  `stock_before` int(11) NOT NULL,
  `stock_after` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `reference_id` varchar(100) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_by` varchar(100) DEFAULT 'System',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `inventory_transactions_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_transactions`
--

LOCK TABLES `inventory_transactions` WRITE;
/*!40000 ALTER TABLE `inventory_transactions` DISABLE KEYS */;
INSERT INTO `inventory_transactions` VALUES (1,1,145,0,145,'initial_import',NULL,'Migrated from inventory.json','System','2026-09-19 17:54:33'),(2,2,12,0,12,'initial_import',NULL,'Migrated from inventory.json','System','2026-09-19 17:54:33'),(3,3,0,0,0,'initial_import',NULL,'Migrated from inventory.json','System','2026-09-19 17:54:33'),(4,4,89,0,89,'initial_import',NULL,'Migrated from inventory.json','System','2026-09-19 17:54:33'),(5,5,250,0,250,'initial_import',NULL,'Migrated from inventory.json','System','2026-09-19 17:54:33'),(6,6,45,0,45,'initial_import',NULL,'Migrated from inventory.json','System','2026-09-19 17:54:33'),(7,1,-2,145,143,'order_placed','16','E2E Test Order #TEST-16646','System','2026-09-19 18:23:36'),(8,1,2,143,145,'order_cancelled','16','E2E Test Cancellation','System','2026-09-19 18:23:37');
/*!40000 ALTER TABLE `inventory_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `subtotal` decimal(10,2) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (1,1,NULL,'Premium Vijaya Extract 1500mg (x2), Sleep Restorative Drops (x1)',4498.00,1,4498.00,NULL,'2026-09-19 17:54:33'),(2,2,NULL,'Premium Vijaya Extract 1500mg (x1)',1499.00,1,1499.00,NULL,'2026-09-19 17:54:33'),(3,3,NULL,'Premium Vijaya Extract 1500mg (x1)',2999.00,1,2999.00,NULL,'2026-09-19 17:54:33'),(4,4,NULL,'Premium Vijaya Extract 1500mg (x1)',999.00,1,999.00,NULL,'2026-09-19 17:54:33'),(5,5,NULL,'Athletic Recovery Tincture (x1), Full Spectrum Pain Relief Balm (x3)',5499.00,1,5499.00,NULL,'2026-09-19 17:54:33'),(6,6,NULL,'Calming Pet CBD Oil (x1), Hemp Hearts Seeds 250g (x1)',1299.00,1,1299.00,NULL,'2026-09-19 17:54:33'),(7,7,NULL,'Premium Vijaya Extract 1500mg (x1)',3499.00,1,3499.00,NULL,'2026-09-19 17:54:33'),(8,8,NULL,'Calming Pet CBD Oil (x1), Hemp Hearts Seeds 250g (x1)',2100.00,1,2100.00,NULL,'2026-09-19 17:54:33'),(9,9,NULL,'Deep Sleep Restorative Drops',1499.00,1,1499.00,'uploads/products/1789195787_pri_Hebe_Drift_Mango__Full_Spectrum_CBD_Gummies_50mg.webp','2026-09-19 17:54:33'),(10,10,NULL,'Deep Sleep Restorative Drops',1499.00,1,1499.00,'uploads/products/1789195787_pri_Hebe_Drift_Mango__Full_Spectrum_CBD_Gummies_50mg.webp','2026-09-19 17:54:33'),(11,11,NULL,'Deep Sleep Restorative Drops',1499.00,1,1499.00,'uploads/products/1789195787_pri_Hebe_Drift_Mango__Full_Spectrum_CBD_Gummies_50mg.webp','2026-09-19 17:54:33'),(12,12,NULL,'Premium Vijaya Extract 1500mg',3999.00,1,3999.00,'uploads/products/1789148297_pri_Hebe_Drift_Mango__Full_Spectrum_Vijaya_Gummies_50_mg.webp','2026-09-19 17:54:33'),(13,13,NULL,'Deep Sleep Restorative Drops',1499.00,1,1499.00,'uploads/products/1789195787_pri_Hebe_Drift_Mango__Full_Spectrum_CBD_Gummies_50mg.webp','2026-09-19 17:54:33'),(14,14,NULL,'Premium Vijaya Extract 1500mg',3999.00,1,3999.00,'uploads/products/1789148297_pri_Hebe_Drift_Mango__Full_Spectrum_Vijaya_Gummies_50_mg.webp','2026-09-19 17:54:33'),(15,15,NULL,'Calming Pet CBD Oil',1299.00,1,1299.00,'uploads/products/1789197055_pri_Hebe-Lift_Berry-Buzz_vijaya_gummies.webp','2026-09-19 17:54:33');
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(100) NOT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(191) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `shipping_address` text NOT NULL,
  `billing_address` text DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `referral_code` varchar(50) DEFAULT NULL,
  `wallet_deduction` decimal(10,2) NOT NULL DEFAULT 0.00,
  `shipping_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `order_status` enum('Pending','Confirmed','Processing','Shipped','Delivered','Cancelled','Refunded') DEFAULT 'Pending',
  `payment_status` enum('Pending','Paid','Failed','Refunded') DEFAULT 'Paid',
  `payment_method` varchar(50) DEFAULT 'COD',
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `order_status` (`order_status`),
  KEY `payment_status` (`payment_status`),
  KEY `created_at` (`created_at`),
  KEY `transaction_id` (`transaction_id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,'ORD-8842',NULL,NULL,'Aman','Kumar','customer@example.com','9990051200','Customer Shipping Address',NULL,4498.00,0.00,NULL,0.00,0.00,0.00,4498.00,'Delivered','Paid','COD',NULL,'2026-09-19 14:24:33','2026-09-19 17:54:33'),(2,'ORD-8843',NULL,NULL,'Priya','Sharma','customer@example.com','9990051200','Customer Shipping Address',NULL,1499.00,0.00,NULL,0.00,0.00,0.00,1499.00,'Processing','Paid','COD',NULL,'2026-09-19 14:24:33','2026-09-19 17:54:33'),(3,'ORD-8844',NULL,NULL,'Rahul','Verma','customer@example.com','9990051200','Customer Shipping Address',NULL,2999.00,0.00,NULL,0.00,0.00,0.00,2999.00,'Delivered','Paid','COD',NULL,'2026-09-19 14:24:33','2026-09-19 17:54:33'),(4,'ORD-8845',NULL,NULL,'Neha','Singh','customer@example.com','9990051200','Customer Shipping Address',NULL,999.00,0.00,NULL,0.00,0.00,0.00,999.00,'Delivered','Paid','COD',NULL,'2026-09-19 14:24:33','2026-09-19 17:54:33'),(5,'ORD-8846',NULL,NULL,'Vikram','Patel','customer@example.com','9990051200','Customer Shipping Address',NULL,5499.00,0.00,NULL,0.00,0.00,0.00,5499.00,'Cancelled','Paid','COD',NULL,'2026-09-19 14:24:33','2026-09-19 17:54:33'),(6,'ORD-8847',NULL,NULL,'Anjali','Desai','customer@example.com','9990051200','Customer Shipping Address',NULL,1299.00,0.00,NULL,0.00,0.00,0.00,1299.00,'Delivered','Paid','COD',NULL,'2026-09-19 14:24:33','2026-09-19 17:54:33'),(7,'ORD-8848',NULL,NULL,'Rohan','Mehta','customer@example.com','9990051200','Customer Shipping Address',NULL,3499.00,0.00,NULL,0.00,0.00,0.00,3499.00,'Refunded','Paid','COD',NULL,'2026-09-19 14:24:33','2026-09-19 17:54:33'),(8,'ORD-8849',NULL,NULL,'Kavita','Iyer','customer@example.com','9990051200','Customer Shipping Address',NULL,2100.00,0.00,NULL,0.00,0.00,0.00,2100.00,'Delivered','Paid','COD',NULL,'2026-09-19 14:24:33','2026-09-19 17:54:33'),(9,'ORD-597348',NULL,1,'Abhinav','singh','namahyatra0@gmail.com','9990051250','Dwarka, New Delhi, Delhi - 110043',NULL,1499.00,0.00,NULL,0.00,0.00,119.92,1618.92,'Processing','Paid','COD',NULL,'2026-09-13 13:13:12','2026-09-19 17:54:33'),(10,'ORD-973115',NULL,1,'New','Delhi','namahyatra0@gmail.com','9990051250','Dwarka, New Delhi, Delhi - 110043',NULL,1499.00,0.00,NULL,0.00,0.00,119.92,1618.92,'Processing','Paid','COD',NULL,'2026-09-13 13:17:36','2026-09-19 17:54:33'),(11,'ORD-252002',NULL,1,'Abhinav','singh','namahyatra0@gmail.com','9990051200','Supernovaaaa, New Delhi, Delhi - 110043',NULL,1499.00,0.00,NULL,0.00,0.00,119.92,1618.92,'Processing','Paid','COD',NULL,'2026-09-13 13:34:49','2026-09-19 17:54:33'),(12,'ORD-848063',NULL,NULL,'refer','mee','abhivivo429@gmail.com','8899778899','Dwarka, New Delhi, Delhi - 110043',NULL,3999.00,0.00,NULL,0.00,0.00,319.92,4318.92,'Processing','Paid','COD',NULL,'2026-09-13 20:01:35','2026-09-19 17:54:33'),(13,'ORD-135739',NULL,NULL,'refer','mee','abhivivo429@gmail.com','8860943144','Dwarka, New Delhi, Delhi - 110043',NULL,1499.00,0.00,NULL,0.00,0.00,107.93,1457.03,'Processing','Paid','COD',NULL,'2026-09-13 20:10:08','2026-09-19 17:54:33'),(14,'ORD-885475',NULL,NULL,'refer','mee','abhivivo429@gmail.com','8860943144','Dwarka, New Delhi, Delhi - 110043',NULL,3999.00,399.90,NULL,0.00,250.00,287.93,4137.03,'Processing','Paid','COD',NULL,'2026-09-13 21:48:30','2026-09-19 17:54:33'),(15,'ORD-630000',NULL,NULL,'Tannu','Singh','leo399644@gmail.com','9876543222','qcdfq, Delhi, Delhi - 110078',NULL,1299.00,129.90,NULL,0.00,250.00,93.53,1512.63,'Processing','Paid','COD',NULL,'2026-09-13 21:53:52','2026-09-19 17:54:33');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `order_number` varchar(100) NOT NULL,
  `transaction_id` varchar(100) NOT NULL,
  `provider_payment_id` varchar(100) DEFAULT NULL,
  `provider` varchar(50) NOT NULL DEFAULT 'native_upi',
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'INR',
  `upi_id` varchar(100) DEFAULT NULL,
  `payment_method` varchar(50) NOT NULL DEFAULT 'UPI',
  `status` enum('PENDING','PROCESSING','SUCCESS','FAILED','CANCELLED','EXPIRED','REFUNDED') NOT NULL DEFAULT 'PENDING',
  `webhook_status` varchar(50) DEFAULT NULL,
  `webhook_payload` longtext DEFAULT NULL,
  `failure_reason` text DEFAULT NULL,
  `checkout_payload` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `paid_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_id` (`transaction_id`),
  KEY `order_id` (`order_id`),
  KEY `order_number` (`order_number`),
  KEY `transaction_id_2` (`transaction_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_analytics`
--

DROP TABLE IF EXISTS `product_analytics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `action_type` enum('view','click','add_to_cart','order') NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `action_type` (`action_type`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_analytics`
--

LOCK TABLES `product_analytics` WRITE;
/*!40000 ALTER TABLE `product_analytics` DISABLE KEYS */;
INSERT INTO `product_analytics` VALUES (1,1,'view',NULL,'127.0.0.1','2026-09-19 18:23:39'),(2,1,'view',NULL,'127.0.0.1','2026-09-19 18:24:06'),(3,5,'add_to_cart',NULL,'::1','2026-09-19 18:32:03'),(4,1,'view',NULL,'127.0.0.1','2026-09-19 18:40:30'),(5,1,'view',NULL,'127.0.0.1','2026-09-19 18:50:58'),(6,5,'add_to_cart',NULL,'::1','2026-09-19 18:53:32'),(7,4,'add_to_cart',NULL,'::1','2026-09-19 18:54:01'),(8,5,'add_to_cart',NULL,'::1','2026-10-03 11:57:44'),(9,2,'view',NULL,'::1','2026-10-03 12:00:55'),(10,2,'add_to_cart',NULL,'::1','2026-10-03 12:01:00'),(11,1,'view',NULL,'127.0.0.1','2026-10-03 12:12:11'),(12,1,'view',NULL,'127.0.0.1','2026-10-03 12:13:14'),(13,1,'view',NULL,'127.0.0.1','2026-10-03 12:13:46'),(14,1,'view',NULL,'127.0.0.1','2026-10-03 12:14:11'),(15,2,'view',NULL,'127.0.0.1','2026-10-03 12:14:11'),(16,3,'view',NULL,'127.0.0.1','2026-10-03 12:14:11'),(17,4,'view',NULL,'127.0.0.1','2026-10-03 12:14:11'),(18,5,'view',NULL,'127.0.0.1','2026-10-03 12:14:11'),(19,6,'view',NULL,'127.0.0.1','2026-10-03 12:14:11'),(20,1,'view',NULL,'127.0.0.1','2026-10-03 12:23:39'),(21,1,'view',NULL,'127.0.0.1','2026-10-03 12:35:16'),(22,1,'view',NULL,'127.0.0.1','2026-10-03 12:36:49'),(23,1,'view',NULL,'127.0.0.1','2026-10-03 12:38:00'),(24,1,'view',NULL,'127.0.0.1','2026-10-03 13:37:39');
/*!40000 ALTER TABLE `product_analytics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_images`
--

DROP TABLE IF EXISTS `product_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `is_secondary` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_images`
--

LOCK TABLES `product_images` WRITE;
/*!40000 ALTER TABLE `product_images` DISABLE KEYS */;
INSERT INTO `product_images` VALUES (29,1,'uploads/products/p1_vijaya_primary.jpg',1,0,1,'2026-10-03 12:02:27'),(30,1,'uploads/products/p1_vijaya_alt.jpg',0,1,2,'2026-10-03 12:02:27'),(31,2,'uploads/products/p2_sleep_primary.jpg',1,0,1,'2026-10-03 12:02:27'),(32,2,'uploads/products/p2_sleep_alt.jpg',0,1,2,'2026-10-03 12:02:27'),(33,3,'uploads/products/p3_balm_primary.jpg',1,0,1,'2026-10-03 12:02:27'),(34,3,'uploads/products/p3_balm_alt.jpg',0,1,2,'2026-10-03 12:02:27'),(35,4,'uploads/products/p4_athletic_primary.jpg',1,0,1,'2026-10-03 12:02:27'),(36,4,'uploads/products/p4_athletic_alt.jpg',0,1,2,'2026-10-03 12:02:27'),(37,5,'uploads/products/p5_pet_primary.jpg',1,0,1,'2026-10-03 12:02:27'),(38,5,'uploads/products/p5_pet_alt.jpg',0,1,2,'2026-10-03 12:02:27'),(39,6,'uploads/products/p6_hemp_primary.jpg',1,0,1,'2026-10-03 12:02:27'),(40,6,'uploads/products/p6_hemp_alt.jpg',0,1,2,'2026-10-03 12:02:27');
/*!40000 ALTER TABLE `product_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `legacy_id` bigint(20) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `sku` varchar(100) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `category_name` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `mrp` decimal(10,2) DEFAULT NULL,
  `sale_badge` varchar(50) DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `status` enum('Active','Low Stock','Out of Stock','Inactive') DEFAULT 'Active',
  `description` text DEFAULT NULL,
  `potency` varchar(50) DEFAULT 'Regular',
  `extract_type` varchar(50) DEFAULT 'Full Spectrum',
  `featured` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `legacy_id` (`legacy_id`),
  KEY `category_id` (`category_id`),
  KEY `status` (`status`),
  KEY `sku` (`sku`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,1,'Premium Vijaya Extract 1500mg','premium-vijaya-extract-1500mg','KAMS-VE-1500',NULL,'Oils & Extracts',2999.00,3999.00,'25% OFF ⚡',150,'Active','Unrefined, full-spectrum Vijaya leaf extract containing high concentrations of CBD, natural minor cannabinoids, and soothing botanical terpenes. Formulated according to Ayurvedic texts for deep bodily equilibrium, stress mitigation, and cellular renewal.','1500mg','Full Spectrum Vijaya',1,'2026-09-19 17:54:33','2026-10-03 13:37:37'),(2,2,'Deep Sleep Restorative Drops','deep-sleep-restorative-drops','KAMS-DS-001',NULL,'Sleep & Recovery',1499.00,2000.00,'25% OFF ⚡',120,'Active','A synergistic botanical formulation uniting organic melatonin, chamomile, and broad-spectrum CBD to support circadian rhythm balance and deep, undisturbed REM sleep cycles without next-day grogginess.','2500mg','Nighttime Terpene Blend',1,'2026-09-19 17:54:33','2026-10-03 12:02:27'),(3,3,'Full Spectrum Pain Relief Balm','full-spectrum-pain-relief-balm','KAMS-PB-500',NULL,'Balms & Topicals',999.00,1299.00,'Bestseller',140,'Active','Rapid-absorption soothing topical balm infused with organic Vijaya extract, arnica montana, camphor, and eucalyptus oil. Provides targeted cooling and warming relief to stiff joints, chronic soreness, and fatigued muscle groups.','500mg','Arnica & Hemp Topical',1,'2026-09-19 17:54:33','2026-10-03 12:02:27'),(4,4,'Athletic Recovery Tincture','athletic-recovery-tincture','KAMS-AR-002',NULL,'Oils & Extracts',3499.00,3999.00,'Pro Athlete',95,'Active','Engineered for athletes, fitness enthusiasts, and demanding active lifestyles. High-concentration organic cannabinoid profile tailored to attenuate post-workout inflammation, combat muscle soreness, and accelerate bodily restoration.','3000mg','Broad Spectrum Recovery',1,'2026-09-19 17:54:33','2026-10-03 12:02:27'),(5,5,'Calming Pet CBD Oil','calming-pet-cbd-oil','KAMS-PT-100',NULL,'Pet Wellness',1299.00,1599.00,'Veterinary Safe',250,'Active','Veterinarian-guided, gentle hemp extract in cold-pressed virgin hemp seed oil. Designed to calm separation anxiety, hyper-reactivity to loud noises, joint stiffness, and age-related discomfort in both dogs and cats.','250mg','Organic Pet Friendly Formula',1,'2026-09-19 17:54:33','2026-10-03 12:02:27'),(6,6,'Hemp Hearts Seeds 250g','hemp-hearts-seeds-250g','KAMS-HH-250',NULL,'Superfoods & Nutrition',499.00,699.00,'Superfood',220,'Active','Raw, de-hulled certified organic hemp seeds packed with complete plant-based protein, all 9 essential amino acids, and the optimal 3:1 ratio of Omega-6 to Omega-3 fatty acids. Perfect addition to smoothies, bowls, and salads.','100% Organic Raw','Cold-hulled Raw Hearts',1,'2026-09-19 17:54:33','2026-10-03 12:02:27');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `referrals`
--

DROP TABLE IF EXISTS `referrals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `referrals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `referrer_id` int(11) NOT NULL,
  `referee_id` int(11) DEFAULT NULL,
  `referral_code` varchar(50) NOT NULL,
  `referee_discount_percent` decimal(5,2) NOT NULL DEFAULT 10.00,
  `referrer_reward_percent` decimal(5,2) NOT NULL DEFAULT 10.00,
  `order_id` int(11) DEFAULT NULL,
  `reward_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('Pending','Completed','Cancelled') DEFAULT 'Completed',
  `created_at` datetime DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `referrer_id` (`referrer_id`),
  CONSTRAINT `referrals_ibfk_1` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referrals`
--

LOCK TABLES `referrals` WRITE;
/*!40000 ALTER TABLE `referrals` DISABLE KEYS */;
INSERT INTO `referrals` VALUES (1,5,6,'AARA3470',10.00,10.00,16,200.00,'Completed','2026-09-19 18:23:36','2026-09-19 18:23:36');
/*!40000 ALTER TABLE `referrals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'store_name','CBD Store','2026-09-19 17:54:33'),(2,'support_email','support@kamshemp.com','2026-09-19 17:54:33'),(3,'support_phone','+91 98765 43210','2026-09-19 17:54:33'),(4,'currency','INR','2026-09-19 17:54:33'),(5,'tax_rate','8','2026-09-19 17:54:33'),(6,'referral_discount_percent','10','2026-09-19 17:54:33'),(7,'referrer_reward_percent','10','2026-09-19 17:54:33'),(8,'max_wallet_usage_percent','50','2026-09-19 17:54:33'),(9,'free_shipping_threshold','3999','2026-09-19 17:54:33'),(10,'standard_delivery_fee','250','2026-09-19 17:54:33'),(11,'express_delivery_fee','300','2026-09-19 17:54:33'),(12,'meta_title','KAMS HEMP | Premium Vedic Cannabis Extracts','2026-09-19 17:54:33'),(13,'meta_description','','2026-09-19 17:54:33'),(14,'ga_tracking_id','G-XXXXXXXXXX','2026-09-19 17:54:33'),(15,'enable_razorpay','1','2026-09-19 17:54:33'),(16,'razorpay_key','','2026-09-19 17:54:33'),(17,'enable_stripe','0','2026-09-19 17:54:33'),(18,'stripe_key','','2026-09-19 17:54:33'),(19,'maintenance_mode','0','2026-09-19 17:54:33'),(20,'require_email_verification','1','2026-09-19 17:54:33'),(21,'two_factor_auth','0','2026-09-19 17:54:33'),(27,'hero_title','Ancient Vedic Healing, Powered by Modern Science','2026-10-03 12:02:27'),(28,'hero_subtitle','Explore India\'s most certified Full-Spectrum Vijaya & CBD extracts. Lab tested, doctor prescribed, and AYUSH compliant.','2026-10-03 12:02:27'),(29,'hero_badge','🌿 100% Certified Organic Vijaya Extract','2026-10-03 12:02:27'),(30,'hero_banner_image','uploads/banners/hero_banner_main.jpg','2026-10-03 12:02:27'),(31,'hero_cta_text','Shop Ayurvedic Extracts','2026-10-03 12:02:27'),(32,'hero_cta_link','#products-grid','2026-10-03 12:02:27'),(33,'announcement_bar_text','⚡ Special Launch Offer: Refer a friend to get 10% OFF, and earn 10% Cashback on EVERY order forever! Free shipping above ₹3,999.','2026-10-03 12:02:27'),(34,'referral_banner_title','Give 10% Discount, Earn 10% Recurring Cashback','2026-10-03 12:02:27'),(35,'referral_banner_subtitle','Share your unique referral link with friends. They get an instant 10% discount on checkout, and you receive 10% cash reward into your wallet on every purchase they ever make!','2026-10-03 12:02:27'),(37,'upi_id','kamshemp@upi','2026-10-03 12:21:22'),(38,'upi_merchant_name','KAMS HEMP India','2026-10-03 12:21:22'),(39,'admin_whatsapp_number','+919876543210','2026-10-03 12:21:22'),(40,'payment_gateway_provider','native_upi','2026-10-03 12:21:22'),(41,'payment_gateway_key','rzp_live_kamshemp','2026-10-03 12:21:22'),(42,'payment_gateway_secret','rzp_sec_kamshemp_live','2026-10-03 12:21:22'),(43,'payment_webhook_secret','whsec_kams_upi_2026','2026-10-03 12:21:22');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `traffic`
--

DROP TABLE IF EXISTS `traffic`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `traffic` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_url` varchar(255) NOT NULL,
  `page_title` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `device_category` enum('desktop','mobile','tablet') DEFAULT 'desktop',
  `source` varchar(100) DEFAULT 'Direct',
  `referrer_url` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_at` (`created_at`),
  KEY `page_url` (`page_url`)
) ENGINE=InnoDB AUTO_INCREMENT=236 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `traffic`
--

LOCK TABLES `traffic` WRITE;
/*!40000 ALTER TABLE `traffic` DISABLE KEYS */;
INSERT INTO `traffic` VALUES (1,'/cbd.php',NULL,'127.0.0.1','','desktop','Direct',NULL,NULL,'2026-09-19 18:23:39'),(2,'/cbd.php',NULL,'127.0.0.1','','desktop','Direct',NULL,NULL,'2026-09-19 18:24:06'),(3,'/ecom/','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct',NULL,NULL,'2026-09-19 18:30:25'),(4,'User Profile','profile','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/',NULL,'2026-09-19 18:30:37'),(5,'User Profile','profile','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/',NULL,'2026-09-19 18:30:48'),(6,'/ecom/','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct',NULL,NULL,'2026-09-19 18:31:48'),(7,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/',NULL,'2026-09-19 18:31:50'),(8,'/ecom/cart.php?action=add&item=Calming+Pet+CBD+Oil','Shopping Cart | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php',NULL,'2026-09-19 18:32:03'),(9,'/ecom/cart.php','Shopping Cart | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php',NULL,'2026-09-19 18:32:03'),(10,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/login.php',NULL,'2026-09-19 18:32:07'),(11,'User Profile','profile','127.0.0.1','','desktop','Direct',NULL,NULL,'2026-09-19 18:32:10'),(12,'/ecom/cbd.php/admin','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct',NULL,NULL,'2026-09-19 18:32:16'),(13,'/ecom/cbd.php/logo.png','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:16'),(14,'/ecom/cbd.php/uploads/CBD%20Store.png','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:16'),(15,'/ecom/cbd.php/uploads/Ananta-Hemp-Work-.png','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:16'),(16,'/ecom/cbd.php/uploads/Cannazo-India-150x150-1.webp','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:16'),(17,'/ecom/cbd.php/uploads/Noigra-150x150-1.webp','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:16'),(18,'/ecom/cbd.php/uploads/cannablithe.png','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:16'),(19,'/ecom/cbd.php/uploads/cf22aff2535c50dd5c71cf1da4099e37.gif','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:16'),(20,'/ecom/cbd.php/uploads/qurist-CBD-150x150-1.webp','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:16'),(21,'/ecom/cbd.php/uploads/products/1789197055_pri_Hebe-Lift_Berry-Buzz_vijaya_gummies.webp','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:16'),(22,'/ecom/cbd.php/uploads/products/1789197055_sec_Hebe_Float_Wild_Watermelon_CBD_THC_Gummies_200_mg_per_gummy.webp','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:16'),(23,'/ecom/cbd.php/uploads/products/1789196410_pri_Hebe_Float_Watermelon_Vijaya_Gummies.webp','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:16'),(24,'/ecom/cbd.php/uploads/products/1789196410_sec_Hebe_Float_Wild_Watermelon_Vijaya_Gummies_200mg_per_gummy.webp','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:16'),(25,'/ecom/cbd.php/uploads/products/1789195821_pri_Hebe_Drift_Mango__Full_Spectrum_CBD_Gummies_50_mg.webp','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:16'),(26,'/ecom/cbd.php/uploads/products/1789195821_sec_Hebe_Drift_Mango__Full_Spectrum_Vijaya_Gummies_50_mg.webp','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:16'),(27,'/ecom/cbd.php/uploads/products/1789195787_pri_Hebe_Drift_Mango__Full_Spectrum_CBD_Gummies_50mg.webp','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:16'),(28,'/ecom/cbd.php/uploads/products/1789195787_sec_Hebe_Drift_Mango__Full_Spectrum_Vijaya_Gummies_50_mg.webp','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:16'),(29,'/ecom/cbd.php/uploads/products/1789149061_pri_Hebe_Drift_Mango__Full_Spectrum_Vijaya_Gummies_50_mg.webp','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:16'),(30,'/ecom/cbd.php/uploads/products/1789149061_sec_Hebe_Drift_Mango__Full_Spectrum_CBD_Gummies_50mg.webp','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:16'),(31,'/ecom/cbd.php/admin','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct',NULL,NULL,'2026-09-19 18:32:28'),(32,'/ecom/cbd.php/uploads/CBD%20Store.png','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:28'),(33,'/ecom/cbd.php/logo.png','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:28'),(34,'/ecom/cbd.php/uploads/Ananta-Hemp-Work-.png','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:28'),(35,'/ecom/cbd.php/uploads/cannablithe.png','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:28'),(36,'/ecom/cbd.php/uploads/Cannazo-India-150x150-1.webp','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:28'),(37,'/ecom/cbd.php/uploads/Noigra-150x150-1.webp','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:28'),(38,'/ecom/cbd.php/uploads/cf22aff2535c50dd5c71cf1da4099e37.gif','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:28'),(39,'/ecom/cbd.php/uploads/qurist-CBD-150x150-1.webp','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:28'),(40,'/ecom/cbd.php/uploads/products/1789197055_pri_Hebe-Lift_Berry-Buzz_vijaya_gummies.webp','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:28'),(41,'/ecom/cbd.php/uploads/products/1789197055_sec_Hebe_Float_Wild_Watermelon_CBD_THC_Gummies_200_mg_per_gummy.webp','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:28'),(42,'/ecom/cbd.php/uploads/products/1789196410_pri_Hebe_Float_Watermelon_Vijaya_Gummies.webp','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:28'),(43,'/ecom/cbd.php/uploads/products/1789196410_sec_Hebe_Float_Wild_Watermelon_Vijaya_Gummies_200mg_per_gummy.webp','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:28'),(44,'/ecom/cbd.php/uploads/products/1789195821_pri_Hebe_Drift_Mango__Full_Spectrum_CBD_Gummies_50_mg.webp','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:28'),(45,'/ecom/cbd.php/uploads/products/1789195821_sec_Hebe_Drift_Mango__Full_Spectrum_Vijaya_Gummies_50_mg.webp','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:28'),(46,'/ecom/cbd.php/uploads/products/1789195787_pri_Hebe_Drift_Mango__Full_Spectrum_CBD_Gummies_50mg.webp','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:28'),(47,'/ecom/cbd.php/uploads/products/1789195787_sec_Hebe_Drift_Mango__Full_Spectrum_Vijaya_Gummies_50_mg.webp','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:28'),(48,'/ecom/cbd.php/uploads/products/1789149061_pri_Hebe_Drift_Mango__Full_Spectrum_Vijaya_Gummies_50_mg.webp','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:28'),(49,'/ecom/cbd.php/uploads/products/1789149061_sec_Hebe_Drift_Mango__Full_Spectrum_CBD_Gummies_50mg.webp','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php/admin',NULL,'2026-09-19 18:32:28'),(50,'/ecom/','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct',NULL,NULL,'2026-09-19 18:32:32'),(51,'User Profile','profile','127.0.0.1','CLI-Test','desktop','Direct',NULL,NULL,'2026-09-19 18:33:35'),(52,'/index.php','Home | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,NULL,'2026-09-19 18:33:57'),(53,'/cbd.php','Home | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,NULL,'2026-09-19 18:33:57'),(54,'/cbd-products.php','Shop All Products | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,NULL,'2026-09-19 18:33:57'),(55,'/cart.php','Shopping Cart | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,NULL,'2026-09-19 18:33:57'),(56,'Secure Checkout','checkout','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,NULL,'2026-09-19 18:33:57'),(57,'User Profile','profile','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,NULL,'2026-09-19 18:33:58'),(58,'Order History','orders','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,NULL,'2026-09-19 18:33:58'),(59,'Order History','orders','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,NULL,'2026-09-19 18:33:58'),(60,'Saved Addresses','addresses','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,NULL,'2026-09-19 18:33:58'),(61,'About Us','about','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,NULL,'2026-09-19 18:33:58'),(62,'Contact Us','contact','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,NULL,'2026-09-19 18:33:58'),(63,'About Us','about','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,NULL,'2026-09-19 18:33:58'),(64,'Contact Us','contact','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,NULL,'2026-09-19 18:33:58'),(65,'/index.php','Home | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,NULL,'2026-09-19 18:35:45'),(66,'/cbd.php','Home | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,NULL,'2026-09-19 18:35:45'),(67,'/cbd-products.php','Shop All Products | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,NULL,'2026-09-19 18:35:45'),(68,'/cart.php','Shopping Cart | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,NULL,'2026-09-19 18:35:46'),(69,'Secure Checkout','checkout','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,NULL,'2026-09-19 18:35:46'),(70,'User Profile','profile','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,NULL,'2026-09-19 18:35:46'),(71,'Order History','orders','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,NULL,'2026-09-19 18:35:46'),(72,'Order History','orders','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,NULL,'2026-09-19 18:35:46'),(73,'Saved Addresses','addresses','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,NULL,'2026-09-19 18:35:46'),(74,'About Us','about','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,NULL,'2026-09-19 18:35:46'),(75,'Contact Us','contact','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,NULL,'2026-09-19 18:35:46'),(76,'About Us','about','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,NULL,'2026-09-19 18:35:47'),(77,'Contact Us','contact','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,NULL,'2026-09-19 18:35:47'),(78,'/index.php','Home | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:36:04'),(79,'/cbd.php','Home | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:36:04'),(80,'/cbd-products.php','Shop All Products | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:36:04'),(81,'/cart.php','Shopping Cart | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:36:04'),(82,'Secure Checkout','checkout','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:36:04'),(83,'User Profile','profile','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:36:04'),(84,'Order History','orders','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:36:04'),(85,'Order History','orders','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:36:04'),(86,'Saved Addresses','addresses','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:36:05'),(87,'About Us','about','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:36:05'),(88,'Contact Us','contact','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:36:05'),(89,'About Us','about','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:36:05'),(90,'Contact Us','contact','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:36:05'),(91,'/index.php','Home | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:37:45'),(92,'/cbd.php','Home | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:37:45'),(93,'/cbd-products.php','Shop All Products | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:37:45'),(94,'/cart.php','Shopping Cart | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:37:45'),(95,'Secure Checkout','checkout','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:37:45'),(96,'User Profile','profile','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:37:45'),(97,'Order History','orders','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:37:45'),(98,'Order History','orders','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:37:46'),(99,'Saved Addresses','addresses','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:37:46'),(100,'About Us','about','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:37:46'),(101,'Contact Us','contact','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:37:46'),(102,'About Us','about','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:37:46'),(103,'Contact Us','contact','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:37:46'),(104,'/index.php','Home | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:39:06'),(105,'/cbd.php','Home | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:39:06'),(106,'/cbd-products.php','Shop All Products | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:39:06'),(107,'/cart.php','Shopping Cart | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:39:06'),(108,'Secure Checkout','checkout','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:39:06'),(109,'User Profile','profile','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:39:07'),(110,'Order History','orders','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:39:07'),(111,'Order History','orders','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:39:07'),(112,'Saved Addresses','addresses','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:39:07'),(113,'About Us','about','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:39:07'),(114,'Contact Us','contact','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:39:07'),(115,'About Us','about','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:39:07'),(116,'Contact Us','contact','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:39:07'),(117,'/index.php','Home | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:39:30'),(118,'/cbd.php','Home | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:39:30'),(119,'/cbd-products.php','Shop All Products | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:39:30'),(120,'/cart.php','Shopping Cart | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:39:31'),(121,'Secure Checkout','checkout','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:39:31'),(122,'User Profile','profile','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:39:31'),(123,'Order History','orders','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:39:32'),(124,'Order History','orders','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:39:32'),(125,'Saved Addresses','addresses','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:39:32'),(126,'About Us','about','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:39:32'),(127,'Contact Us','contact','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:39:32'),(128,'About Us','about','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:39:33'),(129,'Contact Us','contact','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:39:33'),(130,'/cbd.php',NULL,'127.0.0.1','','desktop','Direct',NULL,NULL,'2026-09-19 18:40:30'),(131,'/ecom/','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct',NULL,NULL,'2026-09-19 18:41:26'),(132,'/ecom/','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct',NULL,NULL,'2026-09-19 18:41:27'),(133,'/ecom/','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct',NULL,NULL,'2026-09-19 18:41:27'),(134,'/ecom/','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct',NULL,NULL,'2026-09-19 18:41:28'),(135,'/ecom/','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct',NULL,NULL,'2026-09-19 18:41:28'),(136,'/ecom/','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct',NULL,NULL,'2026-09-19 18:41:28'),(137,'/ecom/','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct',NULL,NULL,'2026-09-19 18:41:28'),(138,'/ecom/','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct',NULL,NULL,'2026-09-19 18:41:28'),(139,'/ecom/','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct',NULL,NULL,'2026-09-19 18:41:28'),(140,'/ecom/','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct',NULL,NULL,'2026-09-19 18:41:28'),(141,'User Profile','profile','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct',NULL,NULL,'2026-09-19 18:43:06'),(142,'User Profile','profile','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/profile.php',NULL,'2026-09-19 18:43:31'),(143,'/index.php','Home | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:50:46'),(144,'/cbd.php','Home | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:50:46'),(145,'/cbd-products.php','Shop All Products | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:50:46'),(146,'/cart.php','Shopping Cart | KAMS HEMP','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:50:46'),(147,'Secure Checkout','checkout','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:50:46'),(148,'User Profile','profile','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:50:47'),(149,'Order History','orders','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:50:47'),(150,'Order History','orders','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:50:47'),(151,'Saved Addresses','addresses','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:50:47'),(152,'About Us','about','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:50:47'),(153,'Contact Us','contact','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:50:47'),(154,'About Us','about','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:50:47'),(155,'Contact Us','contact','127.0.0.1','TestCLI/1.0','desktop','Direct',NULL,1,'2026-09-19 18:50:48'),(156,'/cbd.php',NULL,'127.0.0.1','','desktop','Direct',NULL,NULL,'2026-09-19 18:50:58'),(157,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/inventory.php',NULL,'2026-09-19 18:53:07'),(158,'User Profile','profile','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php',NULL,'2026-09-19 18:53:09'),(159,'User Profile','profile','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/profile.php',NULL,'2026-09-19 18:53:11'),(160,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/admin.php',NULL,'2026-09-19 18:53:13'),(161,'User Profile','profile','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php',NULL,'2026-09-19 18:53:15'),(162,'User Profile','profile','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/profile.php',NULL,'2026-09-19 18:53:21'),(163,'/ecom/cart.php','Shopping Cart | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/profile.php',NULL,'2026-09-19 18:53:22'),(164,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/admin.php',NULL,'2026-09-19 18:53:26'),(165,'User Profile','profile','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php',NULL,'2026-09-19 18:53:28'),(166,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/profile.php',NULL,'2026-09-19 18:53:30'),(167,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php',NULL,'2026-09-19 18:53:30'),(168,'/ecom/cart.php?action=add&item=Calming+Pet+CBD+Oil','Shopping Cart | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php',NULL,'2026-09-19 18:53:32'),(169,'/ecom/cart.php','Shopping Cart | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php',NULL,'2026-09-19 18:53:32'),(170,'/ecom/cart.php','Shopping Cart | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cart.php',NULL,'2026-09-19 18:53:33'),(171,'/ecom/cart.php','Shopping Cart | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cart.php',NULL,'2026-09-19 18:53:36'),(172,'/ecom/cart.php','Shopping Cart | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cart.php',NULL,'2026-09-19 18:53:36'),(173,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/admin.php',NULL,'2026-09-19 18:53:48'),(174,'User Profile','profile','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php',NULL,'2026-09-19 18:53:51'),(175,'/ecom/','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','desktop','Direct',NULL,NULL,'2026-09-19 18:53:59'),(176,'/ecom/cart.php?action=add&item=Athletic+Recovery+Tincture','Shopping Cart | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','desktop','Direct','http://localhost/ecom/',NULL,'2026-09-19 18:54:01'),(177,'/ecom/cart.php','Shopping Cart | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','desktop','Direct','http://localhost/ecom/',NULL,'2026-09-19 18:54:01'),(178,'User Profile','profile','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','desktop','Direct','http://localhost/ecom/login.php',NULL,'2026-09-19 18:54:07'),(179,'User Profile','profile','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','desktop','Direct','http://localhost/ecom/profile.php?tab=register',NULL,'2026-09-19 18:54:49'),(180,'/ecom/','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0','desktop','Direct',NULL,NULL,'2026-10-03 11:40:25'),(181,'User Profile','profile','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0','desktop','Direct','http://localhost/ecom/',NULL,'2026-10-03 11:40:37'),(182,'User Profile','profile','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0','desktop','Direct','http://localhost/ecom/login.php',NULL,'2026-10-03 11:41:03'),(183,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0','desktop','Direct','http://localhost/ecom/profile.php?tab=forgot',NULL,'2026-10-03 11:41:06'),(184,'/ecom/','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0','desktop','Direct',NULL,NULL,'2026-10-03 11:45:35'),(185,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0','desktop','Direct','http://localhost/ecom/',NULL,'2026-10-03 11:55:34'),(186,'/ecom/cart.php?action=add&item=Calming+Pet+CBD+Oil','Shopping Cart | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php',NULL,'2026-10-03 11:57:44'),(187,'/ecom/cart.php','Shopping Cart | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php',NULL,'2026-10-03 11:57:44'),(188,'/ecom/','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0','desktop','Direct',NULL,NULL,'2026-10-03 11:59:36'),(189,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0','desktop','Direct','http://localhost/ecom/',NULL,'2026-10-03 11:59:38'),(190,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0','desktop','Direct','http://localhost/ecom/',NULL,'2026-10-03 12:00:40'),(191,'/ecom/cbd-products.php','Shop All Products | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php',NULL,'2026-10-03 12:00:43'),(192,'/ecom/product_details.php?id=2','Deep Sleep Restorative Drops | CBD Store','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0','desktop','Direct','http://localhost/ecom/cbd-products.php',NULL,'2026-10-03 12:00:55'),(193,'/ecom/cart.php?action=add&id=2&item=Deep+Sleep+Restorative+Drops','Shopping Cart | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0','desktop','Direct','http://localhost/ecom/product_details.php?id=2',NULL,'2026-10-03 12:01:00'),(194,'/ecom/cart.php','Shopping Cart | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0','desktop','Direct','http://localhost/ecom/product_details.php?id=2',NULL,'2026-10-03 12:01:00'),(195,'/cbd.php',NULL,'127.0.0.1','','desktop','Direct',NULL,NULL,'2026-10-03 12:12:11'),(196,'/ecom/cbd.php','Home | KAMS HEMP','127.0.0.1','','desktop','Direct',NULL,NULL,'2026-10-03 12:12:44'),(197,'/ecom/product_details.php?id=1','Premium Vijaya Extract 1500mg | CBD Store','127.0.0.1','','desktop','Direct',NULL,NULL,'2026-10-03 12:13:14'),(198,'/ecom/product_details.php?id=1','Premium Vijaya Extract 1500mg | CBD Store','127.0.0.1','','desktop','Direct',NULL,NULL,'2026-10-03 12:13:46'),(199,'/ecom/product_details.php?id=1','Premium Vijaya Extract 1500mg | CBD Store','127.0.0.1','','desktop','Direct',NULL,NULL,'2026-10-03 12:14:11'),(200,'/ecom/product_details.php?id=2','Deep Sleep Restorative Drops | CBD Store','127.0.0.1','','desktop','Direct',NULL,NULL,'2026-10-03 12:14:11'),(201,'/ecom/product_details.php?id=3','Full Spectrum Pain Relief Balm | CBD Store','127.0.0.1','','desktop','Direct',NULL,NULL,'2026-10-03 12:14:11'),(202,'/ecom/product_details.php?id=4','Athletic Recovery Tincture | CBD Store','127.0.0.1','','desktop','Direct',NULL,NULL,'2026-10-03 12:14:11'),(203,'/ecom/product_details.php?id=5','Calming Pet CBD Oil | CBD Store','127.0.0.1','','desktop','Direct',NULL,NULL,'2026-10-03 12:14:11'),(204,'/ecom/product_details.php?id=6','Hemp Hearts Seeds 250g | CBD Store','127.0.0.1','','desktop','Direct',NULL,NULL,'2026-10-03 12:14:11'),(205,'/ecom/','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0','desktop','Direct',NULL,NULL,'2026-10-03 12:14:16'),(206,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0','desktop','Direct','http://localhost/ecom/',NULL,'2026-10-03 12:21:28'),(207,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php',NULL,'2026-10-03 12:21:31'),(208,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','desktop','Direct',NULL,NULL,'2026-10-03 12:21:47'),(209,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1','mobile','Direct',NULL,NULL,'2026-10-03 12:21:50'),(210,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1','mobile','Direct','http://localhost/ecom/cbd.php',NULL,'2026-10-03 12:21:50'),(211,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','desktop','Direct',NULL,NULL,'2026-10-03 12:22:04'),(212,'/cbd.php',NULL,'127.0.0.1','','desktop','Direct',NULL,NULL,'2026-10-03 12:23:39'),(213,'/cbd.php',NULL,'127.0.0.1','','desktop','Direct',NULL,NULL,'2026-10-03 12:35:16'),(214,'/cbd.php',NULL,'127.0.0.1','','desktop','Direct',NULL,NULL,'2026-10-03 12:36:49'),(215,'Secure Checkout','checkout','127.0.0.1','','desktop','Direct',NULL,NULL,'2026-10-03 12:37:12'),(216,'Secure Checkout','checkout','127.0.0.1','','desktop','Direct',NULL,NULL,'2026-10-03 12:37:23'),(217,'/cbd.php',NULL,'127.0.0.1','','desktop','Direct',NULL,NULL,'2026-10-03 12:38:00'),(218,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','desktop','Direct',NULL,NULL,'2026-10-03 12:46:26'),(219,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','desktop','Direct',NULL,NULL,'2026-10-03 12:46:27'),(220,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','desktop','Direct','http://localhost/ecom/cbd.php',NULL,'2026-10-03 12:46:29'),(221,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','desktop','Direct','http://localhost/ecom/cbd.php',NULL,'2026-10-03 12:47:12'),(222,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','desktop','Direct','http://localhost/ecom/cbd.php',NULL,'2026-10-03 12:53:18'),(223,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','desktop','Direct','http://localhost/ecom/cbd.php',NULL,'2026-10-03 12:54:11'),(224,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1','mobile','Direct','http://localhost/ecom/cbd.php',NULL,'2026-10-03 12:54:14'),(225,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1','mobile','Direct','http://localhost/ecom/cbd.php',NULL,'2026-10-03 12:54:15'),(226,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','desktop','Direct','http://localhost/ecom/cbd.php',NULL,'2026-10-03 12:54:23'),(227,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php',NULL,'2026-10-03 12:55:10'),(228,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','desktop','Direct','http://localhost/ecom/cbd.php',NULL,'2026-10-03 13:08:19'),(229,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1','mobile','Direct','http://localhost/ecom/cbd.php',NULL,'2026-10-03 13:08:23'),(230,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1','mobile','Direct','http://localhost/ecom/cbd.php',NULL,'2026-10-03 13:08:24'),(231,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','desktop','Direct','http://localhost/ecom/cbd.php',NULL,'2026-10-03 13:22:21'),(232,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','desktop','Direct','http://localhost/ecom/cbd.php',NULL,'2026-10-03 13:22:24'),(233,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','desktop','Direct','http://localhost/ecom/cbd.php',NULL,'2026-10-03 13:22:25'),(234,'/cbd.php',NULL,'127.0.0.1','','desktop','Direct',NULL,NULL,'2026-10-03 13:37:39'),(235,'/ecom/cbd.php','Home | KAMS HEMP','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36 Edg/154.0.0.0','desktop','Direct','http://localhost/ecom/cbd.php',NULL,'2026-10-03 13:49:32');
/*!40000 ALTER TABLE `traffic` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_addresses`
--

DROP TABLE IF EXISTS `user_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `legacy_id` varchar(100) DEFAULT NULL,
  `title` varchar(50) DEFAULT 'Home',
  `name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `street` varchar(255) NOT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `zip` varchar(20) NOT NULL,
  `country` varchar(50) DEFAULT 'India',
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_addresses`
--

LOCK TABLES `user_addresses` WRITE;
/*!40000 ALTER TABLE `user_addresses` DISABLE KEYS */;
INSERT INTO `user_addresses` VALUES (1,1,'addr_6aa212d12cc2a','Home','Abhinav','9990051250','Dwarka','New Delhi','Delhi','110043','India',1,'2026-09-19 17:54:33'),(2,1,'addr_6aa6a54a26bcf','Noida','Abhinav singh','9990051200','Supernovaaaa','New Delhi','Delhi','110043','India',0,'2026-09-19 17:54:33');
/*!40000 ALTER TABLE `user_addresses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(191) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `referral_code` varchar(50) NOT NULL,
  `referred_by_user_id` int(11) DEFAULT NULL,
  `has_used_referral` tinyint(1) DEFAULT 0,
  `wallet_balance` decimal(10,2) DEFAULT 0.00,
  `is_verified` tinyint(1) DEFAULT 1,
  `verification_otp` varchar(10) DEFAULT NULL,
  `status` enum('Active','Suspended','Inactive') DEFAULT 'Active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `referral_code` (`referral_code`),
  KEY `referral_code_2` (`referral_code`),
  KEY `email_2` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Abhinav','singh','namahyatra0@gmail.com','9990051200','$2y$10$z1lqxSTGlnpI6.9Qqk.n7OR.4Blkbobr2URN9ztlkxG3CZIL5ha9y','ABHI8506',NULL,0,529.80,1,NULL,'Active','2026-09-19 17:54:33','2026-09-19 17:54:33'),(2,'Anshul','Gupta','iamanshulgupta98@gmail.com','7417458187','$2y$10$5o7i6qKmh60LEXuYq1wX2eazA0QKT1D1B8N52jgLCT1JipwL9MP6i','ANSH5332',NULL,0,0.00,1,NULL,'Active','2026-09-19 17:54:33','2026-09-19 17:54:33'),(3,'Test User','','user@gmail.com','9990051200','$2y$10$F.gJCPGKoRx3WHGSRcHCCeYn3VWqldI3SE335XphY7xgLksPzP0Mi','TEST6265',NULL,0,0.00,1,NULL,'Active','2026-09-19 17:54:33','2026-09-19 17:54:33'),(4,'kaka','singh','dolivexstore@gmail.com','9990051250','$2y$10$J242N8dDJQCqCbHRIsM.ee9OBspIKj.XZCjRh4b7aCfCn4d/NDzmS','KAKA5476',NULL,0,0.00,1,NULL,'Active','2026-09-19 17:54:33','2026-09-19 17:54:33'),(5,'Aarav','Kapoor','test_referrer_1789822416@example.com','9876543299','$2y$10$Wqio78GRifg9/lPFo.1yXeMp3kicORy9vvq3lRUAxUFtwqVywr8mC','AARA3470',NULL,0,200.00,1,NULL,'Active','2026-09-19 18:23:36','2026-09-19 18:23:37'),(6,'Diya','Mehta','test_referee_1789822416@example.com','9123456799','$2y$10$vnOQ2fUkGpmbRgcTZ9yEwuHV8TtjYhujVgg.HDzqGjcRUBw/jPRV6','DIYA2314',5,1,1000.00,1,NULL,'Active','2026-09-19 18:23:36','2026-09-19 18:23:37');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wallet_transactions`
--

DROP TABLE IF EXISTS `wallet_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wallet_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('Credit','Debit') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `balance_after` decimal(10,2) NOT NULL,
  `reference_id` varchar(100) NOT NULL,
  `source` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Completed','Pending','Failed') DEFAULT 'Completed',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `wallet_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wallet_transactions`
--

LOCK TABLES `wallet_transactions` WRITE;
/*!40000 ALTER TABLE `wallet_transactions` DISABLE KEYS */;
INSERT INTO `wallet_transactions` VALUES (1,6,'Credit',1000.00,1000.00,'SYS-1789822416','system','Promotional Welcome Credits','Completed','2026-09-19 18:23:36'),(2,5,'Credit',200.00,200.00,'ORD-16','referral_reward','Referral cashback reward from order #16','Completed','2026-09-19 18:23:37');
/*!40000 ALTER TABLE `wallet_transactions` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-03 14:00:39
