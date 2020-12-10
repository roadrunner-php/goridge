module goridge-example

go 1.15

require github.com/spiral/goridge/v3 v3.0.0-alpha3 // indirect

replace(
	github.com/spiral/goridge/v3 v3.0.0-alpha3 => ../../goridge
)
