CREATE TABLE IF NOT EXISTS `fishing_time` (
    `user_id` bigint(20) NOT NULL,
    `time` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`user_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS `fishing_trophy` (
    `user_id` bigint(20) NOT NULL,
    `type` enum ('fish','hunt','dig') NOT NULL,
    `name` varchar(255) NOT NULL,
    `size` int(11) NOT NULL,
    PRIMARY KEY (`type`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
