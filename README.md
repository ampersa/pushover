# Pushover API Client

Version 0.2

Example:
```
$client = new Pushover;
$response = $client->title('Testing')
                    ->message('Just a quick test of <b>Ampersa\Pushover</b>')
                    ->sound('cashregister')
                    ->priority(Pushover::PRIORITY_EMERGENCY)
                    ->retry(30)
                    ->expires(600)
                    ->html(true)
                    ->url('https://www.google.com', 'Google')
                    ->user('xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx')
                    ->send();

var_export($response);
```

will result in a truth-y response:
```
stdClass::__set_state(array(
   'receipt' => '000000000000000000000000000000',
   'status' => 1,
   'request' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
))
```