csv-to-sepa
===========

Converts a CSV file to a SEPA XML file

The columns in the CSV file must be provided in the following order without headings

- Receiver name
- IBAN
- BIC
- Sum

Command line usage
==================
```
php csvtosepa.php -i csv.csv -o xml.xml --iban AL9020811008000000103953XXXX --bic SPIHAT22XXX --name Your name --info Remittance Information
```

