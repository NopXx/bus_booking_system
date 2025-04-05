CREATE TABLE `User` (
  `user_id` INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `tel` VARCHAR(20) NOT NULL
);

CREATE TABLE `Bus` (
  `bus_id` INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `bus_name` VARCHAR(50) NOT NULL,
  `bus_type` VARCHAR(50) NOT NULL
);

CREATE TABLE `Route` (
  `route_id` INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `source` VARCHAR(50) NOT NULL,
  `destination` VARCHAR(50) NOT NULL,
  `detail` VARCHAR(200)
);

CREATE TABLE `Schedule` (
  `schedule_id` INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `bus_id` INT(11) NOT NULL,
  `route_id` INT(11) NOT NULL,
  `employee_id` INT(11) NOT NULL,
  `date_travel` Date NOT NULL,
  `departure_time` TIME NOT NULL COMMENT 'เวลาออกเดินทาง',
  `arrival_time` TIME NOT NULL COMMENT 'เวลาที่มาถึง',
  `priec` FLOAT NOT NULL COMMENT 'ราคา'
);

CREATE TABLE `Ticket` (
  `ticket_id` INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `schedule_id` INT(11) NOT NULL,
  `employee_id` INT(11) NOT NULL,
  `seat_number` VARCHAR(25),
  `status` VARCHAR(20) NOT NULL,
  `booking_date` Date COMMENT 'วันที่จองตั๋ว',
  `ticket_date` Date COMMENT 'วันที่ออกตั๋ว'
);

CREATE TABLE `employee` (
  `employee_id` INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `tel` VARCHAR(20) NOT NULL
);

ALTER TABLE `Schedule` ADD FOREIGN KEY (`bus_id`) REFERENCES `Bus` (`bus_id`) ON DELETE CASCADE;

ALTER TABLE `Schedule` ADD FOREIGN KEY (`route_id`) REFERENCES `Route` (`route_id`) ON DELETE CASCADE;

ALTER TABLE `Ticket` ADD FOREIGN KEY (`user_id`) REFERENCES `User` (`user_id`) ON DELETE CASCADE;

ALTER TABLE `Ticket` ADD FOREIGN KEY (`schedule_id`) REFERENCES `Schedule` (`schedule_id`) ON DELETE CASCADE;

ALTER TABLE `Ticket` ADD FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE CASCADE;

ALTER TABLE `Schedule` ADD FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE CASCADE;
