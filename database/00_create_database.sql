-- =====================================================================
-- Vulcatic - LOCAL ONLY (MySQL Workbench)
-- Run this first on your own computer to create the database.
--
-- DO NOT import this file on InfinityFree / phpMyAdmin.
-- InfinityFree creates the database for you (e.g. if0_12345678_vulcatic)
-- from the control panel; select it in phpMyAdmin, then import
-- 01_schema.sql and (optionally) 02_seed.sql.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS vulcatic
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE vulcatic;
