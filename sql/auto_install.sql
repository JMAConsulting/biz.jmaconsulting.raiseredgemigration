CREATE TABLE `re_error_data` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `column_name` VARCHAR(32) NOT NULL,
  `table_name` VARCHAR(32) NOT NULL,
  `parameters` TEXT NOT NULL,
  `error_message` TEXT NOT NULL,
  PRIMARY KEY (`id`)) ENGINE = InnoDB;
