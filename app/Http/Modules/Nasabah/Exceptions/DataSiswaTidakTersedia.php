<?php

namespace App\Http\Modules\Nasabah\Exceptions;

use RuntimeException;

/**
 * API data siswa sekolah tidak dapat dihubungi atau membalas tidak wajar.
 */
class DataSiswaTidakTersedia extends RuntimeException {}
