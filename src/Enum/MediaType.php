<?php
declare(strict_types=1);
namespace Oem\Psr7WhatsappMediaCrypto\Enum;

enum MediaType: string
{
    case IMAGE = 'WhatsApp Image Keys';
    case VIDEO = 'WhatsApp Video Keys';
    case AUDIO = 'WhatsApp Audio Keys';
    case DOCUMENT = 'WhatsApp Document Keys';
}