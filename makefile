#
# Makefile
#
zip:
	zip -r woocommerce-pricewaiter.zip ./ \
	-x '.*' \
	-x '*~' \
	-x '*.DS_Store' \
	-x '*.zip' \
	-x 'makefile' \
	-x 'README.md';
