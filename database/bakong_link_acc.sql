CREATE DATABASE IF NOT EXISTS `bakong_link_account`;

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `tblinit`;
CREATE TABLE `tblinit` (
    `token` varchar(256) NOT NULL,
    `key_num` varchar(256) DEFAULT NULL,
    `cif` varchar(10) NOT NULL,
    `loginType` varchar(256) DEFAULT NULL,
    `loginPhoneNumber` varchar(30) DEFAULT NULL,
    `key` varchar(64) DEFAULT NULL,
    `bakongAccId` varchar(60) DEFAULT NULL,
    `phoneNumber` varchar(30) DEFAULT NULL,
    `created_Date` varchar(256) DEFAULT NULL,
    `expired_Date` varchar(256) DEFAULT NULL,
    PRIMARY KEY (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `tbllinkaccount`;
CREATE TABLE `tbllinkaccount` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `account` varchar(50) unique NOT NULL,
    `token` varchar(256)  ,
    `cif` varchar(10) NOT NULL,
    CONSTRAINT `fk_tblinit` FOREIGN KEY (`token`) REFERENCES `tblinit`(`token`) ON UPDATE CASCADE ON DELETE CASCADE ,
    PRIMARY KEY(`id`)
)ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `tblunlink`;
CREATE TABLE `tblunlink` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cif` varchar(10) NOT NULL,
  `loginPhoneNumber` varchar(30) DEFAULT NULL,
  `key` varchar(64) DEFAULT NULL,
  `bakongAccId` varchar(60) DEFAULT NULL,
  `phoneNumber` varchar(30) DEFAULT NULL,
  `accNumber` varchar(50) DEFAULT NULL,
  `created_Date` varchar(256) DEFAULT NULL,
  `unlink_Date` varchar(256) DEFAULT NULL,
  PRIMARY KEY(`id`)
)ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `tbltransaction`;
CREATE TABLE  `tbltransaction` (
  `refNumber` varchar(32) NOT NULL,
  `transId` varchar(256) DEFAULT NULL,
  `transHash` varchar(256) DEFAULT NULL,
  `transType` varchar(256) DEFAULT NULL,
  `fromAcc` varchar(30) DEFAULT NULL,
  `toAcc` varchar(30) DEFAULT NULL,
  `amount` varchar(256) DEFAULT NULL,
  `currency` varchar(256) DEFAULT NULL,
  `fee` varchar(256) DEFAULT NULL,
  `dbtCdt` varchar(256) DEFAULT NULL,
  `desc` varchar(30) DEFAULT NULL,
  `status` varchar(256) DEFAULT NULL,
  `transDate` varchar(256) DEFAULT NULL,
  PRIMARY KEY (`refNumber`)
)ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `tbllimitation`;
CREATE TABLE `tbllimitation` (
  `minimum` varchar(256) NOT NULL,
  `maximum` varchar(256) NOT NULL,
  `fee` varchar(256),
  `currency` varchar(256) NOT NULL
)ENGINE=InnoDB DEFAULT CHARSET=utf8;

TRUNCATE TABLE `tbllimitation`;
INSERT INTO `tbllimitation` VALUES('0','50','0','USD');
INSERT INTO `tbllimitation` VALUES('50','500','0.5','USD');
INSERT INTO `tbllimitation` VALUES('500','1500','1.00','USD');
INSERT INTO `tbllimitation` VALUES('1500','2500','1.50','USD');
INSERT INTO `tbllimitation` VALUES('2500','5000','2.00','USD');
INSERT INTO `tbllimitation` VALUES('5000','10000','2.50','USD');

INSERT INTO `tbllimitation` VALUES('0','200000','0','KHR');
INSERT INTO `tbllimitation` VALUES('200000','2000000','2000','KHR');
INSERT INTO `tbllimitation` VALUES('2000000','6000000','4000','KHR');
INSERT INTO `tbllimitation` VALUES('6000000','10000000','6000','KHR');
INSERT INTO `tbllimitation` VALUES('10000000','20000000','8000','KHR');
INSERT INTO `tbllimitation` VALUES('20000000','40000000','10000','KHR');
