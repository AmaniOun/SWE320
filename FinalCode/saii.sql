-- phpMyAdmin SQL Dump
-- version 5.1.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 23, 2026 at 05:06 PM
-- Server version: 5.7.24
-- PHP Version: 8.3.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `saii`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `AdminID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`AdminID`, `UserID`) VALUES
(1, 1),
(2, 2);

-- --------------------------------------------------------

--
-- Table structure for table `booking`
--

CREATE TABLE `booking` (
  `BookingID` int(11) NOT NULL,
  `BookingDate` date NOT NULL,
  `SeatNumber` int(11) NOT NULL,
  `BookingStatus` enum('Confirmed','Cancelled') NOT NULL,
  `PilgrimID` int(11) NOT NULL,
  `TripID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `booking`
--

INSERT INTO `booking` (`BookingID`, `BookingDate`, `SeatNumber`, `BookingStatus`, `PilgrimID`, `TripID`) VALUES
(1, '2025-05-01', 12, 'Confirmed', 1, 1),
(2, '2025-05-03', 7, 'Confirmed', 2, 1),
(3, '2025-05-06', 23, 'Confirmed', 3, 1),
(4, '2025-05-08', 5, 'Confirmed', 1, 2),
(5, '2026-04-05', 33, 'Confirmed', 2, 3),
(6, '2026-04-08', 13, 'Cancelled', 2, 5),
(7, '2026-04-10', 9, 'Confirmed', 3, 4),
(8, '2026-04-11', 4, 'Cancelled', 3, 7),
(9, '2026-04-12', 22, 'Confirmed', 1, 8),
(10, '2026-04-12', 33, 'Confirmed', 2, 8),
(11, '2026-04-13', 44, 'Confirmed', 3, 8),
(12, '2026-04-16', 7, 'Confirmed', 1, 4);

-- --------------------------------------------------------

--
-- Table structure for table `bus`
--

CREATE TABLE `bus` (
  `BusID` int(11) NOT NULL,
  `Bus_Number` varchar(50) NOT NULL,
  `Capacity` int(11) NOT NULL,
  `Status` enum('Active','Maintenance') NOT NULL,
  `Driver_Name` varchar(100) NOT NULL,
  `Driver_Phone` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `bus`
--

INSERT INTO `bus` (`BusID`, `Bus_Number`, `Capacity`, `Status`, `Driver_Name`, `Driver_Phone`) VALUES
(1, 'BUS-001', 30, 'Active', 'Faisal Al-Harbi', '0501111111'),
(2, 'BUS-002', 50, 'Active', 'Nasser Al-Dossari', '0502222222'),
(3, 'BUS-003', 45, 'Active', 'Saad Al-Mutairi', '0503333333'),
(4, 'BUS-004', 20, 'Maintenance', 'Turki Al-Anazi', '0504444444'),
(5, 'BUS-005', 50, 'Active', 'Bandar Al-Rashidi', '0505555555');

-- --------------------------------------------------------

--
-- Table structure for table `heatmap`
--

CREATE TABLE `heatmap` (
  `HeatmapID` int(11) NOT NULL,
  `location` varchar(150) NOT NULL,
  `crowdDensity` decimal(5,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `heatmap`
--

INSERT INTO `heatmap` (`HeatmapID`, `location`, `crowdDensity`) VALUES
(1, 'Masjid Al-Haram', '95.50'),
(2, 'Arafat', '88.00'),
(3, 'Mina', '99.00'),
(4, 'Jamarat', '75.30'),
(5, 'Muzdalifah', '70.10'),
(6, 'Aziziyah', '97.80');

-- --------------------------------------------------------

--
-- Table structure for table `notification`
--

CREATE TABLE `notification` (
  `notification_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `TripID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `notification`
--

INSERT INTO `notification` (`notification_id`, `message`, `sent_at`, `TripID`) VALUES
(1, 'Traffic congestion', '2025-05-01 10:05:00', 1),
(2, 'Road maintenance', '2025-05-07 20:00:00', 2);

-- --------------------------------------------------------

--
-- Table structure for table `pilgrim`
--

CREATE TABLE `pilgrim` (
  `PilgrimID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `pilgrim`
--

INSERT INTO `pilgrim` (`PilgrimID`, `UserID`) VALUES
(1, 3),
(2, 4),
(3, 5);

-- --------------------------------------------------------

--
-- Table structure for table `qrcode`
--

CREATE TABLE `qrcode` (
  `QR_Seq` int(11) NOT NULL,
  `BookingID` int(11) NOT NULL,
  `QR_Value` varchar(255) NOT NULL,
  `GeneratedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ExpiryTime` datetime NOT NULL,
  `QR_Status` enum('Active','Used','Expired') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `qrcode`
--

INSERT INTO `qrcode` (`QR_Seq`, `BookingID`, `QR_Value`, `GeneratedAt`, `ExpiryTime`, `QR_Status`) VALUES
(1, 1, 'QR-SAII-BK001', '2025-05-01 09:15:00', '2025-05-08 05:00:00', 'Expired'),
(2, 2, 'QR-SAII-BK002', '2025-05-03 11:40:00', '2025-05-08 05:00:00', 'Expired'),
(3, 3, 'QR-SAII-BK003', '2025-05-06 08:25:00', '2025-05-08 05:00:00', 'Expired'),
(4, 4, 'QR-SAII-BK004', '2025-05-08 14:10:00', '2025-05-10 07:00:00', 'Expired'),
(5, 5, 'QR-SAII-BK005', '2026-04-05 10:55:00', '2026-06-12 08:00:00', 'Active'),
(6, 6, 'QR-SAII-BK006', '2026-04-08 16:20:00', '2026-06-10 03:00:00', 'Active'),
(7, 7, 'QR-SAII-BK007', '2026-04-10 07:05:00', '2026-06-10 19:30:00', 'Active'),
(8, 8, 'QR-SAII-BK008', '2026-04-11 19:45:00', '2026-06-15 09:00:00', 'Active'),
(9, 9, 'QR-SAII-BK009', '2026-04-12 13:30:00', '2026-06-19 15:00:00', 'Active'),
(10, 10, 'QR-SAII-BK010', '2026-04-12 18:05:00', '2026-06-19 15:00:00', 'Active'),
(11, 11, 'QR-SAII-BK011', '2026-04-13 09:50:00', '2026-06-19 15:00:00', 'Active'),
(12, 12, 'QR-SAII-BK012', '2026-04-16 21:10:00', '2026-06-10 19:30:00', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `trip`
--

CREATE TABLE `trip` (
  `TripID` int(11) NOT NULL,
  `Origin` varchar(100) NOT NULL,
  `Destination` varchar(100) NOT NULL,
  `DepartureDate` date NOT NULL,
  `DepartureTime` time NOT NULL,
  `TotalSeats` int(11) NOT NULL,
  `AvailableSeats` int(11) NOT NULL,
  `Status` enum('Confirmed','Completed','Cancelled') NOT NULL,
  `Pickup_Location` varchar(150) DEFAULT NULL,
  `BusID` int(11) NOT NULL,
  `AdminID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `trip`
--

INSERT INTO `trip` (`TripID`, `Origin`, `Destination`, `DepartureDate`, `DepartureTime`, `TotalSeats`, `AvailableSeats`, `Status`, `Pickup_Location`, `BusID`, `AdminID`) VALUES
(1, 'Masjid Al-Haram', 'Mina', '2025-05-08', '04:00:00', 30, 27, 'Completed', 'King Fahd Gate', 1, 1),
(2, 'Makkah Hotel Zone', 'Mina', '2025-05-10', '06:00:00', 50, 49, 'Completed', 'Ibrahim Al-Khalil Pickup', 2, 1),
(3, 'Mina', 'Arafat', '2026-06-12', '07:00:00', 45, 44, 'Confirmed', 'Mina Camp Gate 3', 3, 2),
(4, 'Arafat', 'Muzdalifah', '2026-06-10', '18:30:00', 45, 43, 'Confirmed', 'Namira Mosque Exit', 3, 2),
(5, 'Muzdalifah', 'Aziziyah', '2026-06-10', '02:00:00', 30, 29, 'Cancelled', 'Muzdalifah Zone B', 1, 1),
(6, 'Mina', 'Masjid Al-Haram', '2026-06-13', '10:00:00', 35, 35, 'Confirmed', 'Mina Camp Gate 1', 2, 2),
(7, 'Masjid Al-Haram', 'Mina', '2026-06-15', '08:00:00', 50, 50, 'Confirmed', 'King Fahd Gate', 5, 1),
(8, 'Jamarat', 'Makkah Hotel Zone', '2026-06-19', '14:00:00', 50, 47, 'Confirmed', 'Bab Salam Pickup', 5, 2);

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `UserID` int(11) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `User_Name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`UserID`, `Email`, `Password`, `User_Name`) VALUES
(1, 'moh@saii.sa', '$2y$10$qqjgDqsQ0.QO4SXILf2lQeZHmeVD8j0Rjo5qwhY3CzLB63Zt4cF16', 'Mohammed Al-Otaibi'),
(2, 'kha@saii.sa', '$2y$10$Eq2Gtwh6ewdyF.TmGrTRf.hgj5U/6qKPdhMqt2tWborxbEK4C1Mny', 'Khalid Al-Ghamdi'),
(3, 'Ahmed@gmail.com', '$2y$10$g20QEKSCMTs62n842P8JeeB7a8ymBtdNTf8BY.OvpQI/CBw6JdhRC', 'Ahmed Al-Zahrani'),
(4, 'Omar@gmail.com', '$2y$10$vAysI08Az6JpuGE.vjZTPuab40yrMo2.d.PmCbD8HIxNGflLlgtFG', 'Omar Al-Shehri'),
(5, 'Yusuf@gmail.com', '$2y$10$jBVDb.dR5IJmpdHnO8lKBedBkCs.RV8ZEcJF5XWSAZfnxMuGAdhZG', 'Yusuf Al-Qahtani');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`AdminID`),
  ADD KEY `UserID` (`UserID`);

--
-- Indexes for table `booking`
--
ALTER TABLE `booking`
  ADD PRIMARY KEY (`BookingID`),
  ADD KEY `PilgrimID` (`PilgrimID`),
  ADD KEY `TripID` (`TripID`);

--
-- Indexes for table `bus`
--
ALTER TABLE `bus`
  ADD PRIMARY KEY (`BusID`);

--
-- Indexes for table `heatmap`
--
ALTER TABLE `heatmap`
  ADD PRIMARY KEY (`HeatmapID`);

--
-- Indexes for table `notification`
--
ALTER TABLE `notification`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `TripID` (`TripID`);

--
-- Indexes for table `pilgrim`
--
ALTER TABLE `pilgrim`
  ADD PRIMARY KEY (`PilgrimID`),
  ADD KEY `UserID` (`UserID`);

--
-- Indexes for table `qrcode`
--
ALTER TABLE `qrcode`
  ADD PRIMARY KEY (`QR_Seq`),
  ADD KEY `BookingID` (`BookingID`);

--
-- Indexes for table `trip`
--
ALTER TABLE `trip`
  ADD PRIMARY KEY (`TripID`),
  ADD KEY `BusID` (`BusID`),
  ADD KEY `AdminID` (`AdminID`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`UserID`),
  ADD UNIQUE KEY `Email` (`Email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `AdminID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `booking`
--
ALTER TABLE `booking`
  MODIFY `BookingID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `bus`
--
ALTER TABLE `bus`
  MODIFY `BusID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `heatmap`
--
ALTER TABLE `heatmap`
  MODIFY `HeatmapID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `notification`
--
ALTER TABLE `notification`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pilgrim`
--
ALTER TABLE `pilgrim`
  MODIFY `PilgrimID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `qrcode`
--
ALTER TABLE `qrcode`
  MODIFY `QR_Seq` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `trip`
--
ALTER TABLE `trip`
  MODIFY `TripID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `UserID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin`
--
ALTER TABLE `admin`
  ADD CONSTRAINT `admin_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `user` (`UserID`);

--
-- Constraints for table `booking`
--
ALTER TABLE `booking`
  ADD CONSTRAINT `booking_ibfk_1` FOREIGN KEY (`PilgrimID`) REFERENCES `pilgrim` (`PilgrimID`),
  ADD CONSTRAINT `booking_ibfk_2` FOREIGN KEY (`TripID`) REFERENCES `trip` (`TripID`);

--
-- Constraints for table `notification`
--
ALTER TABLE `notification`
  ADD CONSTRAINT `notification_ibfk_1` FOREIGN KEY (`TripID`) REFERENCES `trip` (`TripID`);

--
-- Constraints for table `pilgrim`
--
ALTER TABLE `pilgrim`
  ADD CONSTRAINT `pilgrim_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `user` (`UserID`);

--
-- Constraints for table `qrcode`
--
ALTER TABLE `qrcode`
  ADD CONSTRAINT `qrcode_ibfk_1` FOREIGN KEY (`BookingID`) REFERENCES `booking` (`BookingID`);

--
-- Constraints for table `trip`
--
ALTER TABLE `trip`
  ADD CONSTRAINT `trip_ibfk_1` FOREIGN KEY (`BusID`) REFERENCES `bus` (`BusID`),
  ADD CONSTRAINT `trip_ibfk_2` FOREIGN KEY (`AdminID`) REFERENCES `admin` (`AdminID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
