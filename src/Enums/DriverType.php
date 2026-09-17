<?php

namespace Websyspro\Connection\Enums;

enum DriverType
{
  case MySql;
  case Sqlite;
  case PostgreSQL;
  case SqlServer;
}