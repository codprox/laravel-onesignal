# Laravel-OneSignal

Package Laravel optimisé pour interconnecter votre backend avec OneSignal, permettant l'envoi de notifications push, la gestion de segments, et l'inscription d'utilisateurs de manière simple et performante.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/codprox/laravel-onesignal.svg?style=flat-square)](https://packagist.org/packages/codprox/laravel-onesignal)
[![Total Downloads](https://img.shields.io/packagist/dt/codprox/laravel-onesignal.svg?style=flat-square)](https://packagist.org/packages/codprox/laravel-onesignal)
[![License](https://img.shields.io/packagist/l/codprox/laravel-onesignal.svg?style=flat-square)](https://packagist.org/packages/codprox/laravel-onesignal)

## Prérequis

- PHP >= 8.2
- Laravel >= 11.0
- Compte OneSignal avec un `App ID` et une `REST API Key`

## Installation

Installez le package via Composer :

```bash
composer require codprox/laravel-onesignal


## Configuration

```Publiez le fichier de configuration (Cela créera un fichier config/onesignal.php dans votre projet) :
php artisan vendor:publish --tag=onesignal-config

```Ajoutez les variables suivantes à votre fichier .env :
ONESIGNAL_APP_ID=your_app_id
ONESIGNAL_REST_API_KEY=your_rest_api_key
ONESIGNAL_DEFAULT_ICON=https://your-site.com/default-icon.png
ONESIGNAL_CACHE_TTL=3600
ONESIGNAL_TIMEOUT=10.0
ONESIGNAL_CONNECT_TIMEOUT=5.0

```Le fichier config/onesignal.php contient les options par défaut :
return [
    'app_id' => env('ONESIGNAL_APP_ID'),
    'rest_api_key' => env('ONESIGNAL_REST_API_KEY'),
    'default_icon' => env('ONESIGNAL_DEFAULT_ICON', 'https://example.com/icon.png'),
    'cache_ttl' => env('ONESIGNAL_CACHE_TTL', 3600), // Durée en secondes
    'timeout' => env('ONESIGNAL_TIMEOUT', 10.0), // Timeout HTTP en secondes
    'connect_timeout' => env('ONESIGNAL_CONNECT_TIMEOUT', 5.0), // Timeout de connexion
];


## Utilisation

```Le package fournit une classe MyOneSignal accessible via une façade ou par injection de dépendance.

*** Via la Façade

use Codprox\OneSignal\Facades\OneSignal;
OneSignal::sendToAll(['Subject', 'Body'], ['badge_count' => 1]);

*** Via Injection de Dépendance

use Codprox\OneSignal\MyOneSignal; 
class NotificationController extends Controller
{
    protected MyOneSignal $oneSignal;

    public function __construct(MyOneSignal $oneSignal)
    {
        $this->oneSignal = $oneSignal;
    }

    public function send()
    {
        $this->oneSignal->sendToAll(['Test', 'Hello World']);
    }
}


## Méthodes Disponibles


1. sendToAll(array $message, array $extraData = [], ?string $scheduledTime = null): array 

2. 


