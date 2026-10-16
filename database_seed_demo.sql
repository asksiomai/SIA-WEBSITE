-- OPTIONAL demo data for the existing `volunteer_management` database.
-- Inserts rows only (no table or column changes). Import AFTER volunteer_management.sql.
-- Every demo account uses the password:  password123

INSERT INTO `users` (`user_id`,`last_name`,`first_name`,`middle_name`,`email`,`password`,`role`,`phone`,`address`,`interests`,`availability`) VALUES
(1,'Admin','Super',NULL,'superadmin@cvms.local','$2y$12$ZWt.LPNmQ68ZwG1DxwMrUe1GfVw1/xjCKOZ5UMNmejqwXF9uHZ7Gy','super_admin','09170000001','Barangay Hall',NULL,NULL),
(2,'Santos','Maria','L.','organizer@cvms.local','$2y$12$ZWt.LPNmQ68ZwG1DxwMrUe1GfVw1/xjCKOZ5UMNmejqwXF9uHZ7Gy','organizer','09170000002','Pasig City','community, teaching',NULL),
(3,'Dela Cruz','Juan','P.','juan@cvms.local','$2y$12$ZWt.LPNmQ68ZwG1DxwMrUe1GfVw1/xjCKOZ5UMNmejqwXF9uHZ7Gy','volunteer','09170000003','Pasig City','computers, teaching, education','Weekends'),
(4,'Reyes','Ana',NULL,'ana@cvms.local','$2y$12$ZWt.LPNmQ68ZwG1DxwMrUe1GfVw1/xjCKOZ5UMNmejqwXF9uHZ7Gy','volunteer','09170000004','Taguig City','environment, clean-up','Saturday, Sunday'),
(5,'Garcia','Mark',NULL,'mark@cvms.local','$2y$12$ZWt.LPNmQ68ZwG1DxwMrUe1GfVw1/xjCKOZ5UMNmejqwXF9uHZ7Gy','volunteer','09170000005','Marikina City','computer troubleshooting, technology','Weekdays'),
(6,'Lim','Carla',NULL,'carla@cvms.local','$2y$12$ZWt.LPNmQ68ZwG1DxwMrUe1GfVw1/xjCKOZ5UMNmejqwXF9uHZ7Gy','volunteer','09170000006','Pasig City','health, community','Anytime'),
(7,'Torres','Paolo',NULL,'paolo@cvms.local','$2y$12$ZWt.LPNmQ68ZwG1DxwMrUe1GfVw1/xjCKOZ5UMNmejqwXF9uHZ7Gy','volunteer','09170000007','Quezon City','sports',NULL);

INSERT INTO `skills` (`skill_id`,`skill_name`,`description`) VALUES
(1,'Computer Literacy','Basic to advanced use of computers and office software'),
(2,'Teaching','Explaining and training others'),
(3,'Computer Troubleshooting','Fixing common computer problems'),
(4,'First Aid','Basic emergency care'),
(5,'Event Organizing','Planning and running community events'),
(6,'Clean-up Drives','Waste segregation and community clean-up');

INSERT INTO `volunteer_skills` (`user_id`,`skill_id`,`proficiency_level`) VALUES
(3,1,'Advanced'),(3,2,'Intermediate'),
(5,3,'Advanced'),(5,2,'Beginner'),(5,1,'Intermediate'),
(4,6,'Advanced'),(4,5,'Beginner'),
(6,4,'Intermediate'),(6,5,'Intermediate'),
(7,1,'Beginner');

INSERT INTO `activities` (`activity_id`,`created_by`,`activity_name`,`description`,`activity_date`,`location`,`max_volunteers`) VALUES
(1,2,'Community Computer Training','Free computer basics training for community members','2026-10-10','Barangay Learning Center',3),
(2,2,'Community Clean-Up Drive','Neighborhood clean-up and waste segregation','2026-10-17','Riverside Park',5),
(3,2,'Barangay Health Day','Basic health assistance and community event support','2026-09-12','Barangay Hall',3);

INSERT INTO `activity_requirements` (`activity_id`,`skill_id`,`required_level`) VALUES
(1,1,'Intermediate'),(1,2,'Beginner'),
(2,6,'Beginner'),(2,5,'Beginner'),
(3,4,'Intermediate'),(3,5,'Beginner');

-- Past activity with history (so Experience, Hours and Reports have data)
INSERT INTO `assignments` (`assignment_id`,`activity_id`,`user_id`,`role`,`assignment_status`) VALUES
(1,3,6,'First Aid Assistant','Completed'),
(2,3,4,'Registration Desk','Completed'),
(3,3,7,'Logistics','Assigned');

INSERT INTO `attendance` (`assignment_id`,`attendance_date`,`check_in_time`,`check_out_time`,`hours_rendered`,`status`,`remarks`) VALUES
(1,'2026-09-12','08:00:00','12:00:00',4.00,'Present',NULL),
(2,'2026-09-12','08:30:00','12:00:00',3.50,'Late','Arrived 30 minutes late'),
(3,'2026-09-12',NULL,NULL,0.00,'Absent','Did not attend');
