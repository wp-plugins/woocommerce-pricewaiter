#
# Makefile
#
zip:
	mkdir woocommerce-pricewaiter
	rsync -vr --exclude-from=makefile-exclude . woocommerce-pricewaiter 
	cp README.md woocommerce-pricewaiter/readme.txt
	zip -r ../woocommerce-pricewaiter-v1.0.zip woocommerce-pricewaiter
	rm -rf woocommerce-pricewaiter
