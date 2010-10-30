USE [your_database];

CREATE TABLE `attachments`(
	id					CHAR(36)			NOT NULL
	, model			VARCHAR(255)	NOT NULL
	, entity_id	CHAR(36) 			NOT NULL
	, alias     VARCHAR(255)	NOT NULL DEFAULT 'File'
	, path			VARCHAR(255)	NOT NULL
	, uri				VARCHAR(255)	NOT NULL
	, mimetype	VARCHAR(255)	NOT NULL DEFAULT 'text/unknown'
	, size			INT						NOT NULL DEFAULT -1
	, created		DATETIME			NOT NULL
	, modified	DATETIME			NOT NULL
	, PRIMARY KEY( id )
)
ENGINE=InnoDB;

/**
CREATE TABLE images(
	id					CHAR(36)			NOT NULL
	, width			INT						NOT NULL DEFAULT -1
	, height		INT						NOT NULL DEFAULT -1
	, created		DATETIME			NOT NULL
	, modified	DATETIME			NOT NULL
	, PRIMARY KEY( id )
)
ENGINE=InnoDB;
*/

GRANT ALL ON [your_database].binaries to [user] IDENTIFIED BY '[password]';
