# Pushover API Client (for Laravel 5)

Version 0.1

Installation (Laravel 5):
```
composer require ampersa/pushover
```

in config/app.php, add the following line to the service providers array
```
Ampersa\Pushover\PushoverServiceProvider::class,
```

and add the following to the aliases array:
```
'Pushover' => Ampersa\Pushover\Facades\Pushover::class
```