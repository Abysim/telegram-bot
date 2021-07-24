CREATE TABLE IF NOT EXISTS `banned_words` (
    `word` varchar(255) NOT NULL,
    PRIMARY KEY (`word`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS `brain`(
    `k1` int(10) UNSIGNED NOT NULL,
    `k2` int(10) UNSIGNED NOT NULL,
    `k3` int(10) UNSIGNED NOT NULL,
    `k4` int(10) UNSIGNED NOT NULL,
    `k5` int(10) UNSIGNED NOT NULL,
    UNIQUE KEY `k1` (`k1`, `k2`, `k3`, `k4`, `k5`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS `responses` (
    `trig` int(10) UNSIGNED NOT NULL,
    `resp` int(10) UNSIGNED NOT NULL,
    PRIMARY KEY (`trig`, `resp`) USING BTREE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE IF NOT EXISTS `words` (
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `word` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `word` (`word`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

INSERT INTO `words` (`id`, `word`) VALUES
(0, ''),
(1, '\n'),
(2, 'VUFFUN');


