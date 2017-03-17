# Sandstorm.TemplateMailer - Simple Template-Based Emails for Neos and Flow

## Features
This package works in Neos CMS and Flow and provides the following functionality:

* Simple creation and sending of template-based emails
* Automatic inlining of CSS into the email body, making it look good in clients like Gmail as well

## Compatibility and Maintenance
Sandstorm.TemplateMailer is currently being maintained for Neos 3.x / Flow 4.x.

| Neos / Flow Version        | Sandstorm.TemplateMailer Version | Maintained |
|----------------------------|----------------------------------|------------|
| Neos 3.x, Flow 4.x         | 1.x                              | Yes        |

# Configuration and Usage

## Configuring the package
This package provides 2 config options.

### Configuring global sender addresses
By adding entries to the `senderAddresses` config array, you can define sender addresses
in your config and connect them to a string identifier. This allows for easy global maintenance
of email sender addresses and names. Override the "default" entry to just have one global
sender address that's automatically used everywhere without you having to do anything else.

### Configuring template source packages
You need to tell TemplateMailer in which packages it should look for email templates. Do this by adding an
entry to the `templatePackages` array, like so:
```YAML
Sandstorm:
  TemplateMailer:
    templatePackages:
      10: 'Your.Package'
```
If you have multiple packages that contain email templates, add them all in the order you want TemplateMailer
to search them for templates. Lower numbers as keys mean that this package is checked earlier. If a template
with the given name is found in a package, it is used. This way, you can create an override hierarchy.

### Default Template Variables
You can expose configuration settings as default template variables to all email templates. We use this to
expose the base Uri by default, but you can pass arbitrary settings paths here and they will be resolved.

## Using the package
Create an "EmailTemplates" folder in your package's `Resources/Private` folder. In it, create as many email templates as
you want. 

***IMPORTANT:*** You _must_ create a .txt and an .html file with the same name for each template-based mail you want to send.

You can use partials and layouts as usual in Fluid. If you do, put them in `Resources/Private/EmailTemplates/Partials`
or `Resources/Private/EmailTemplates/Layouts` respectively.

### Basic usage
A very basic usage without variables looks like this. Your template must not contain any variables (as you aren't passing in any)
and TemplateMailer will use the "default" sender address, which you should configure beforehand.
```PHP
$this->emailService->sendTemplateEmail(
    'YourTemplateFileName',
    'An arbitrary email title',
    ['recipient@example.com']
);

```

### Advanced usage
You can use a different configured sender address as well as pass variables to the template. 
You need to have configured the sender email 'mysender' before.
```PHP
$this->emailService->sendTemplateEmail(
    'YourTemplateFileName',
    'An arbitrary email title',
    ['recipient@example.com'],
    [
        'var1' => 'Foo',
        'var2' => 5
    ],
    'mysender'
);

```

If you pass an array to the `sendTemplateEmail()` method, we'll pass it right through to SwiftMailer so you can 
use sender email addresses that haven't been configured before.
```PHP
$this->emailService->sendTemplateEmail(
    'YourTemplateFileName',
    'An arbitrary email title',
    ['recipient@example.com'],
    ['sender@example.com' => 'Your Service Name']
);

```
