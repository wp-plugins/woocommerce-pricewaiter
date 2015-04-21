### Available Filters

##### Disable PriceWaiter on product

```
function my_product_disable_pricewaiter($pricewaiter_disabled, $product) {

    return $pricewaiter_disabled;

}
add_filter('pw_product_disable_pricewaiter', 'my_product_disable_pricewaiter', '', 2);
```

##### Supported Product Types 
(default: simple, variable)

```
function my_pw_supported_product_types($supported, $product) {
    
    return $supported;

}
add_filter('pw_supported_product_types', 'my_pw_supported_product_types', '', 2);
```

##### Widget Script Priority
(defaults to: 10?)
 
```
function my_pw_widget_script_priority($priority) {
    
    return $priority;

}
add_filter('pw_widget_script_priority', 'my_pw_widget_script_priority');
```

##### Order IPN Callback
[Mock script example](https://gist.github.com/taeo/be3207a3472f55cd516a)

```
function my_pricewaiter_ipn_endpoint($url) {
    return 'http://woo.pricewaiter.dev/ipntest.php';
}
add_filter('pricewaiter_ipn_endpoint', 'my_pricewaiter_ipn_endpoint');
```