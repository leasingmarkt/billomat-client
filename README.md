# A PHP Billomat API Client

[![Latest Stable Version](https://poser.pugx.org/leasingmarkt/billomat-client/v/stable.png)](https://packagist.org/packages/leasingmarkt/billomat-client) [![License](https://poser.pugx.org/vrok/billomat-client/license.png)](https://packagist.org/packages/vrok/billomat-client)

## Introduction

This is a Billomat PHP client for interacting with the REST Billomat API.

## Extend of this client

All methods on the following assets are supported:

* [Clients](http://www.billomat.com/en/api/clients)
  * [Properties](http://www.billomat.com/en/api/clients/properties)
* [Articles](http://www.billomat.com/en/api/articles)
  * [Properties](http://www.billomat.com/en/api/articles/properties)
* [Invoices](http://www.billomat.com/en/api/invoices)
  * [Items](http://www.billomat.com/en/api/invoices/items)
  * [Payments](http://www.billomat.com/en/api/invoices/payments)
  * [Tags](http://www.billomat.com/en/api/invoices/tags)
* [Credit Notes](http://www.billomat.com/en/api/credit-notes)
  * [Items](http://www.billomat.com/en/api/credit-notes/items)
  * [Payments](http://www.billomat.com/en/api/credit-notes/payments)
* [Templates](http://www.billomat.com/en/api/templates)
* ~~Settings~~
  * [Article Properties](http://www.billomat.com/en/api/settings/article-properties)
  * [Client Properties](http://www.billomat.com/en/api/settings/client-properties)
  * [User Properties](http://www.billomat.com/en/api/settings/user-properties)
  * [Taxes](http://www.billomat.com/en/api/settings/taxes)
  * [Tax free countries](http://www.billomat.com/en/api/settings/tax-free-countries)
  * [Dunning Levels](http://www.billomat.com/en/api/settings/reminder-texts)
  * [Email Templates](http://www.billomat.com/en/api/settings/email-vorlagen)

## Dependencies

* [Guzzle library](http://guzzlephp.org): >= 7.2
* PHP >= 7.4, ext-curl

## Installation via composer

```sh
composer require leasingmarkt/billomat-client
```

## How to use this

Instantiate the client:

```php
use Phobetor\Billomat\Client\BillomatClient;

$billomat = new BillomatClient('my-id', 'my-api-key');
```

If you have a registered app for billomat’s API (which is highly recommended due to higher
rate limits) instantiate the client like this:

```php
use Phobetor\Billomat\Client\BillomatClient;

$billomat = new BillomatClient('my-id', 'my-api-key', 'my-api-app-id', 'my-api-app-secret');
```

The client will find the correct endpoint automatically based on your id.

All methods are accessible from that client object:

```php
// Get the client with id 133713371337
$client = $billomat->getClient(array(
    'id' => 133713371337
));

// Create a new client
$client = $billomat->createClient(array(
    'client' => array(
        'number' => 424242424242,
        'name' => 'client-name'
    )
));
```

### Calling methods

All method names are based on the Billomat API URLs and follow the [CRUD](http://de.wikipedia.org/wiki/CRUD) naming schema whenever possible.

All parameter names are exactly mapped. Therefore, you can refer to the [official API documentation](http://www.billomat.com/en/api).
Deep links to specific documentation sections are given in the [extend of this client](#extend-of-this-client) list above and the [complete method reference](#complete-method-reference) list below.

Here is the user update method as an example for the [client](http://www.billomat.com/en/api/clients) asset:

```php
// Update a client
$client = $billomat->updateClient(array(
    'id' => 133713371337,
    'client' => array(
        'number' => 424242424242,
        'name' => 'client-name'
    )
));
```

### Return values

All `delete*` methods return nothing.

The methods `get*Pdf` (when called with `'format' => 'pdf'`) and `getTemplatePreview` return a guzzle response. You can easily extract the file content:

```php
// Fetch an invoice pdf file
$response = $billomat->getInvoicePdf(array(
    'id' => 133713371337,
    'format' => 'pdf'
));
$content = (string)$response->getBody();
```

All the other methods return `array` values.

### Exception handling

This client creates exceptions from Billomat errors based on the HTTP status code and filled with the error message provided by the Billomat API.
All exceptions are `GuzzleHttp\Command\Exception\CommandException` and contain a
`Phobetor\Billomat\Exception\ExceptionInterface` as `$e->getPrevious()`, 
so you can catch this to handle everything.
You can find all exceptions in the `Phobetor\Billomat\Exception` folder.

Usage example:

```php
try {
    $client = $billomat->updateClient(array(
        'id' => 133713371337,
        'client' => array(
            'number' => 424242424242,
            'name' => 'client-name'
        )
    ));
}
catch (GuzzleHttp\Command\Exception\CommandException $e) {
    switch (get_class($e->getPrevious())) {
        case \Phobetor\Billomat\Exception\NotFoundException::class: 
            // There seems to be no such client.
            break;
        case \Phobetor\Billomat\Exception\BadRequestException::class:
            // Some of the given data must have been bad. $e->getMessage() could help.
            breaK;
        case \Phobetor\Billomat\Exception\TooManyRequestsException::class:
            // The rate limit was reached. $e->getRateLimitReset() returns the UTC timestamp of the next rate limit reset.
            // @see http://www.billomat.com/en/api/basics/rate-limiting for details about the rate limit.
            breaK;
        default:
            // Something else failed. Maybe there is no connection to the API servers.
            break;
    }
}
```

### Automatic rate limit handling

If this client is used in asynchronous processes or CLI commands you can activate automatic waiting for rate limit reset.
In that mode all method calls that would otherwise throw a `\Phobetor\Billomat\Exception\TooManyRequestsException` will wait for the rate limit reset and retry automatically.
You **should not** use this in synchronous request (e. g. a website request) because all method calls in that mode can last very long and most likely longer than your server’s request timeout.
There are two ways to do this.

At construction:

```php
use Phobetor\Billomat\Client\BillomatClient;

// setting the fifth parameter to true enables waiting for rate limit
$billomat = new BillomatClient('my-id', 'my-api-key', 'my-api-app-id', 'my-api-app-secret', true);
```

After construction:

```php
$billomat->setDoWaitForRateLimitReset(true);
```

### Complete method reference

CLIENT RELATED METHODS [doc](http://www.billomat.com/en/api/clients):
* array getClients(array $args = [])
* array getClient(array $args = [])
* array getClientMyself(array $args = [])
* array createClient(array $args = [])
* array updateClient(array $args = [])
* void deleteClient(array $args = [])

CLIENT PROPERTY VALUE RELATED METHODS [doc](http://www.billomat.com/en/api/clients/properties):
* array getClientPropertyValues(array $args = [])
* array getClientPropertyValue(array $args = [])
* array setClientPropertyValue(array $args = [])

ARTICLE RELATED METHODS [doc](http://www.billomat.com/en/api/articles):
* array getArticles(array $args = [])
* array getArticle(array $args = [])
* array createArticle(array $args = [])
* array updateArticle(array $args = [])
* void deleteArticle(array $args = [])

ARTICLE PROPERTY VALUE RELATED METHODS [doc](http://www.billomat.com/en/api/articles/properties):
* array getArticlePropertyValues(array $args = [])
* array getArticlePropertyValue(array $args = [])
* array setArticlePropertyValue(array $args = [])

INVOICE RELATED METHODS [doc](http://www.billomat.com/en/api/invoices):
* array getInvoices(array $args = [])
* array getInvoice(array $args = [])
* array createInvoice(array $args = [])
* array updateInvoice(array $args = [])
* array completeInvoice(array $args = [])
* \Guzzle\Http\Message\Response getInvoicePdf(array $args = [])
* array signInvoice(array $args = [])
* array sendInvoiceEmail(array $args = [])
* array cancelInvoice(array $args = [])
* array undoCancelInvoice(array $args = [])
* void deleteInvoice(array $args = [])

INVOICE ITEM RELATED METHODS [doc](http://www.billomat.com/en/api/invoices/items):
* array getInvoiceItems(array $args = [])
* array getInvoiceItem(array $args = [])
* array createInvoiceItem(array $args = [])
* array updateInvoiceItem(array $args = [])
* void deleteInvoiceItem(array $args = [])

INVOICE PAYMENT RELATED METHODS [doc](http://www.billomat.com/en/api/invoices/payments):
* array getInvoicePayments(array $args = [])
* array getInvoicePayment(array $args = [])
* array createInvoicePayment(array $args = [])
* array deleteInvoicePayment(array $args = [])

INVOICE TAG RELATED METHODS [doc](http://www.billomat.com/en/api/invoices/tags):
* array getInvoiceTagCloud(array $args = [])
* array getInvoiceTags(array $args = [])
* array getInvoiceTag(array $args = [])
* array createInvoiceTag(array $args = [])
* array deleteInvoiceTag(array $args = [])

CREDIT NOTE RELATED METHODS [doc](http://www.billomat.com/en/api/credit-notes):
* array getCreditNotes(array $args = [])
* array getCreditNote(array $args = [])
* array createCreditNote(array $args = [])
* array updateCreditNote(array $args = [])
* array completeCreditNote(array $args = [])
* \Guzzle\Http\Message\Response getCreditNotePdf(array $args = [])
* array signCreditNote(array $args = [])
* array sendCreditNoteEmail(array $args = [])
* void deleteCreditNote(array $args = [])

CREDIT NOTE ITEM RELATED METHODS [doc](http://www.billomat.com/en/api/credit-notes/items):
* array getCreditNoteItems(array $args = [])
* array getCreditNoteItem(array $args = [])
* array createCreditNoteItem(array $args = [])
* array updateCreditNoteItem(array $args = [])
* void deleteCreditNoteItem(array $args = [])

CREDIT NOTE PAYMENT RELATED METHODS [doc](http://www.billomat.com/en/api/invoices/credit-notes):
* array getCreditNotePayments(array $args = [])
* array getCreditNotePayment(array $args = [])
* array createCreditNotePayment(array $args = [])
* array deleteCreditNotePayment(array $args = [])

TEMPLATE RELATED METHODS [doc](http://www.billomat.com/en/api/templates):
* array getTemplates(array $args = [])
* array getTemplate(array $args = [])
* \Guzzle\Http\Message\Response getTemplatePreview(array $args = [])
* array createTemplate(array $args = [])
* array updateTemplate(array $args = [])
* void deleteTemplate(array $args = [])

ARTICLE PROPERTY RELATED METHODS [doc](http://www.billomat.com/en/api/settings/article-properties):
* array getArticleProperties(array $args = [])
* array getArticleProperty(array $args = [])
* array createArticleProperty(array $args = [])
* array updateArticleProperty(array $args = [])
* void deleteArticleProperty(array $args = [])

CLIENT PROPERTY RELATED METHODS [doc](http://www.billomat.com/en/api/settings/client-properties):
* array getClientProperties(array $args = [])
* array getClientProperty(array $args = [])
* array createClientProperty(array $args = [])
* array updateClientProperty(array $args = [])
* void deleteClientProperty(array $args = [])

USER PROPERTY RELATED METHODS [doc](http://www.billomat.com/en/api/settings/user-properties):
* array getUserProperties(array $args = [])
* array getUserProperty(array $args = [])
* array createUserProperty(array $args = [])
* array updateUserProperty(array $args = [])
* void deleteUserProperty(array $args = [])

TAX RELATED METHODS [doc](http://www.billomat.com/en/api/settings/taxes):
* array getTaxes(array $args = [])
* array getTax(array $args = [])
* array createTax(array $args = [])
* array updateTax(array $args = [])
* void deleteTax(array $args = [])

COUNTRY TAX RELATED METHODS [doc](http://www.billomat.com/en/api/settings/tax-free-countries):
* array getCountryTaxes(array $args = [])
* array getCountryTax(array $args = [])
* array createCountryTax(array $args = [])
* array updateCountryTax(array $args = [])
* void deleteCountryTax(array $args = [])

REMINDER TEXT RELATED METHODS [doc](http://www.billomat.com/en/api/settings/reminder-texts):
* array getReminderTexts(array $args = [])
* array getReminderText(array $args = [])
* array createReminderText(array $args = [])
* array updateReminderText(array $args = [])
* void deleteReminderText(array $args = [])

EMAIL TEMPLATE RELATED METHODS [doc](http://www.billomat.com/en/api/settings/email-vorlagen):
* array getEmailTemplates(array $args = [])
* array getEmailTemplate(array $args = [])
* array createEmailTemplate(array $args = [])
* array updateEmailTemplate(array $args = [])
* void deleteEmailTemplate(array $args = [])

USER PROPERTY VALUE RELATED METHODS [doc](http://www.billomat.com/en/api/users/properties):
* array getUserPropertyValues(array $args = [])
* array getUserPropertyValue(array $args = [])
* array setUserPropertyValue(array $args = [])

SUPPLIER RELATED METHODS [doc](https://www.billomat.com/en/api/suppliers/):
* array getSupplier(array $args = [])
* array getSuppliers(array $args = [])

INCOMING INVOICES [doc](https://www.billomat.com/en/api/incomings/):
* array getIncomings(array $args = [])
* array getIncomingPdf(array $args = [])

INCOMING INVOICE TAGS [doc](http://www.billomat.com/en/api/incomings/tags):
* array createIncomingTag(array $args = [])

INCOMING PAYMENTS [doc](https://www.billomat.com/en/api/incomings/payments/):  
* array createIncomingPayment(array $args = [])

INCOMING DOCUMENTS [doc](http://www.billomat.com/en/api/incomings/inbox):
* array createIncomingInboxDocument(array $args = [])

### API glitches handled by this client internally

The Billomat API provides two data formats, xml and json. The json format is used here. Due to an xml to json conversion in the Billomat API lists have a data inconsistency in the json responses.

If there is ony one element in a list the API returns something like this:
```php
array(
    'clients' => array(
        'client' => array(
            'id' => 133713371337,
            /* […] */
        ),
    ),
)
```

If there are more elements in a list the API returns something like this:
```php
array(
    'clients' => array(
        'client' => array(
            array(
                'id' => 133713371337,
                /* […] */
            ),
            array(
                'id' => 133713371338,
                /* […] */
            ),
            /* […] */
        ),
    ),
)
```

The type of `$result['clients']['client']` changes from an associative array to a numeric array of associative arrays.

This issue is addressed by this client internally. You can be sure that lists are numeric arrays (like in the lower example) no matter how many elements are returned.

## Advanced usage

This client is built on top of Guzzle, so you can take advantage of all its features. Please refer to the [Guzzle documentation](http://docs.guzzlephp.org/en/latest/) to learn more …
