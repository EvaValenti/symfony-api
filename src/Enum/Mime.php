<?php

namespace App\Enum;

enum Mime: string {
    case jpeg = 'image/jpeg';
    case png = 'image/png';
    case svg = 'image/svg+xml';
    }