<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class VideoService
{
    protected array $platforms = [
        'youtube' => [
            'pattern' => '/(?:youtube\.com\/(?:
