<?php namespace Maddhatter\PrettyQr\Facades;

use Illuminate\Support\Facades\Facade;

class PrettyQr extends Facade{
	protected static function getFacadeAccessor() { return 'pretty-qr'; }
}