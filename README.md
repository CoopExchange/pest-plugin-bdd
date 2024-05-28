# Installation

Firstly, until this repo is public, you need to add it to composer.json as a private repo:
```php
"repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/CoopExchange/pest-plugin-bdd"
        }
    ],
```

and then require it with composer using the below:

```php
composer require coop-exchange/pest-plugin-bdd
```

# How to use
This plugin expects a feature file (ending `.feature` e.g. `ExampleTest.feature`) for every Pest test, with the exact
same name (i.e. only the extension changes from `.php` to `.feature`).
The feature and related test files should live in the directory `tests/Feature/BDD`.

You run the command by adding the `--bdd` parameter, e.g.
```php
vendor/bin/pest --bdd
```

The above command will check all `.feature` files have a related test file and have the correct tests inside. 
Once run you can then run the test suit normal, e.g.:

```php
vendor/bin/pest
```

You can also add `--create-tests` which will create the test files that are missing:
```php
vendor/bin/pest --bdd --create-tests
```

# About BDD
---
** Major credit to [behat.org](https://behat.org/en/latest/user_guide/writing_scenarios.html) for most of the below **
---

## Features
Feature files contain one or more scenarios to 'illustrate' the feature. Scenarios contain steps.

There are three types of steps:
- Given
- When
- Then

Within each test file that is created, each scenario is created as an ['it'](https://pestphp.com/docs/writing-tests#content-your-first-test) 
and they are all wrapped within a ['describe' block](https://pestphp.com/docs/pest-spicy-summer-release#content-describe-blocks)

## Scenarios
TODO Docs

## Scenario Outlines
TODO Docs

Mention examples become datasets

## Given
The purpose of the Given steps is to put the system in a known state before the user (or external system) starts 
interacting with the system (in the When steps). 
---
** Avoid talking about user interaction in givens **
---
If you have worked with use cases, givens are your preconditions.

Examples:
```gherkin
Given there are no users on site
Given the database is clean
Given I am logged in as "Everzet"
```

### Arguments
You can add table arguments, like the following:
```gherkin
Given there are users:
| username | password | email               |
| everzet  | 123456   | everzet@knplabs.com |
| fabpot   | 22@222   | fabpot@symfony.com  |
```

The plugin will automatically create the following in your test file:
```php
test('Given there are no applications in this tenant, but there is an application in another tenant with the following', function () {
    $data = new \Illuminate\Support\Collection([
       ['Name' => 'Another tenants application', 'Description' => 'Description of another tenants application'],
    ]);

    // Insert test for this step here
})->todo();
```

## When
The purpose of When steps is to describe the key action the user performs (or, using Robert C. Martin’s metaphor, 
the state transition).

```gherkin
When I am on "/some/page"
When I fill "username" with "everzet"
When I fill "password" with "123456"
When I press "login"
```

## Then
The purpose of Then steps is to observe outcomes. The observations should be related to the business value/benefit 
in your feature description. The observations should inspect the output of the system (a report, user interface, 
message, command output) and not something deeply buried inside it (that has no business value and is instead part 
of the implementation).

```gherkin
When I call "echo hello"
Then the output should be "hello"

When I send an email with:
  """
  ...
  """
Then the client should receive the email with:
  """
  ...
  """
```

---
** While it might be tempting to implement Then steps to just look in the database – resist the temptation. 
You should only verify output that is observable by the user (or external system). 
Database data itself is only visible internally to your application, but is then finally exposed by the output 
of your system in a web browser, on the command-line or an email message. **
---

## And & But
These are used to combine multiple Given, When or Then.

Instead of this
```gherkin
Scenario: Multiple Givens
  Given one thing
  Given another thing
  Given yet another thing
  When I open my eyes
  Then I see something
  Then I don't see something else
```

Write this:
```gherkin
Scenario: Multiple Givens
  Given one thing
  And another thing
  And yet another thing
  When I open my eyes
  Then I see something
  But I don't see something else
```

## Background

** TODO (possibly distinguish between before each background and before all background? **

Backgrounds allows you to add some context to all scenarios in a single feature.

```gherkin
Background:
    Given a global administrator named "Greg"
    And a blog named "Greg's anti-tax rants"
    And a customer named "Wilson"
    And a blog named "Expensive Therapy" owned by "Wilson"
```

Background are inserted into beforeEach function within each feature (actually within the describe)



# TODO

-[ ] Insert handling of running for a single file, or group of files? It always runs against all files in the directory.
-[ ] Groups / Tags - set `@something` in the gherkin to `->group('something')` or `@something @somethingelse` in the gherkin to `->group('something', 'somethingelse')`
-[ ] As above for tags for whole files `uses()->group('feature');`
-[ ] Before each before all etc - and maybe after each and after all?
-[ ] Report on functions within an 'it' but NOT with an equivalent step in the feature file
-[ ] handle editing steps with parameters (at the moment it creates a new step/function)
