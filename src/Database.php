<?php

namespace Websyspro\Connection;

use Websyspro\Connection\Enums\DriverType;
use Websyspro\Utils\Collection;
use PDOException;
use PDOStatement;
use Exception;
use PDO;

use function sprintf;
use function defined;
use function sizeof;

class Database
{
	private static PDO $handle;
	private static array $statements = [];

	public static function connect(
	): PDO {
		if( isset( static::$handle )){
			return static::$handle;
		}

		if( CONNECT_DETAILS->driver === DriverType::Sqlite ){
			static::$handle = new PDO(
				self::getSqlite()
			);
		} else {
			static::$handle = new PDO(
				match( CONNECT_DETAILS->driver ){
					DriverType::PostgreSQL => self::getPostgresSQL(),
					DriverType::SqlServer => self::getSqlServer(),
					DriverType::MySql => self::getMySQL(),
						default => self::getPdoException(),
				},
				CONNECT_DETAILS->user,
				CONNECT_DETAILS->pass, self::getPdoOptions()
			);
		}


		return static::$handle;
	}

	private static function getMySQL(
	): string {
		return sprintf( "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
			CONNECT_DETAILS->host, CONNECT_DETAILS->port, CONNECT_DETAILS->name
		);
	}

	private static function getPostgresSQL(
	): string {
		return sprintf( "pgsql:host=%s;port=%s;dbname=%s",
			CONNECT_DETAILS->host, CONNECT_DETAILS->port, CONNECT_DETAILS->name
		);
	}
	
	private static function getSqlite(
	): string {
		return sprintf( "sqlite:%s",
			CONNECT_DETAILS->name
		);
	}
	
	private static function getSqlServer(
	): string {
		return sprintf( "sqlsrv:Server=%s,%s;Database=%s;TrustServerCertificate=1",
			CONNECT_DETAILS->host, CONNECT_DETAILS->port, CONNECT_DETAILS->name
		);
	}

	private static function getPdoException(
	): PDOException {
		return throw new PDOException(
			sprintf( "Driver '%s' nao suportado", CONNECT_DETAILS->driver->name )
		);
	}

	private static function getPdoOptions(
	): array {
		return [
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_EMULATE_PREPARES => false,
		];
	}

	public static function driver(
	): DriverType {
		if( defined( "CONNECT_DETAILS" ) === false ){}
		return CONNECT_DETAILS->driver;
	}


  public static function query(
		string $sql,
		array $params = [],
		string|null $typeof = null,
		Collection|array $fetchAll = [],
	): Collection|array {
		try {
			static::connect();

			if( isset( static::$statements[ $sql ]) === false ) {
				static::$statements[ $sql ] = static::$handle->prepare( $sql );
			}

			$statements = static::$statements[ $sql ];
			if( $statements instanceof PDOStatement ){
				if( $statements->execute( $params )){
					$fetchAll = $statements->fetchAll(
						$typeof !== NULL 
							? PDO::FETCH_ASSOC
							: PDO::FETCH_OBJ
					);
				}
			}
			
			if( $typeof !== null ){
				$fetchAll = Collection::create( $fetchAll )->mapper(
					fn( array $row ) => new $typeof( ...$row )
				);
			}

			if( sizeof( static::$statements ) >= 2056 ){
				static::$statements = [];
			}
			
			$statements->closeCursor();
			return $fetchAll;
		} catch( PDOException $pdoException ){
			return throw new Exception( $pdoException->getMessage() );
		};
  }

	public static function execute(
		string $sql, 
		array $params = [],
		int $rowCount = 0
	): int {
		try {
			static::connect();
	
			if( isset( static::$statements[$sql]) === false ){
				static::$statements[ $sql ] = static::$handle->prepare( $sql );
			}
	
			$statements = static::$statements[$sql];
			if( $statements->execute( $params )){
				$rowCount = $statements->rowCount();
			}
			
			$statements->closeCursor();
			return $rowCount;
		} catch( PDOException $pdoException ){

		}

		return 0;
	}	

	public static function set(
		string $sql
	): int {
		try {
			static::connect();
			return static::$handle->exec( $sql );
		} catch( PDOException $pdoException ){
			return throw new Exception( $pdoException->getMessage() );
		}
	}	

	public static function lastInsertId(
	): string {
		return static::$handle->lastInsertId();
	}
}
