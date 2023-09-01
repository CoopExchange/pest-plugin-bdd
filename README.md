# Installation

```php
composer require vmeretail/pest-plugin-bdd
```

# How to use
This plugin expects a feature file (ending `.feature` e.g. `ExampleTest.feature`) for every Pest test, with the exact
same name (i.e. only the extension changes from `.php` to `.feature`)

You run the command by adding the `--bdd` parameter, e.g.
```php
vendor/bin/pest --bdd
```

You can also add `--create-tests` which will not only create Test php files, but amend existing ones to reflect any
changes in the feature file:
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

The plugin will automatically create the following in your test file (and update it should you change the table):
```php
// Note this function will be inserted into your 'it' test
function step_10ca07b4_5cd0026e_there_are_users()
{
    $data = [
        ["username" => 'everzet', "password" => '123456', "email" => 'everzet@knplabs.com'],
        ["username" => 'fabpot', "password" => '22@222', "email" => 'fabpot@symfony.com'],
    ];

    // Insert test for this step here
}

step_10ca07b4_5cd0026e_there_are_users();
```

Note the `step_10ca07b4_5cd0026e_` is for internal use to ensure two functions are never the same name.

- `step_` is hardcoded to reflect this is a step (as opposed to a user defined function in your test)
- `10ca07b4` is a crc32 hash of the filename
- `5cd0026e` is a crc32 hash of the scenario name

This is the code that creates it:
```php
$fileHash = hash('crc32', $testFilename);
$scenarioHash = hash('crc32', $scenarioTitle);
$requiredStepname = 'step_' . $fileHash . '_' . $scenarioHash . '_' . $stepname;
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
