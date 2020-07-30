# Verja

Configurable CVE notifications to Jira and Slack for private repos.

Note: This project is inspired by https://github.com/y-mehta/vulnalerts.

## Slack

To run this Github action with Slack you need to create an [incoming Slack webhook](https://slack.com/intl/en-gb/help/articles/115005265063-Incoming-webhooks-for-Slack).

## Jira

To use this with Jira, you need to have a Jira user with API access.

## Setup

Create a private repo on Github (you need a private repo because you are storing secrets in the repo).

### CPE.txt

Add a cpe.txt file in repo root, add 1 project per line. I would recommend that you are granular, if you add `php`, you will get a lot of alerts from php-based projects. So better to use sometling like: `php:php:7`, if you want alerts for PHP 7.x. 

To find CPE to use, go to https://nvd.nist.gov/products/cpe and download Official CPE Dictionary. The search functionality is not so great.

### config.yml

Add a config.yml in the repo root with this format:

```yaml
# config for verja
slack:
  enabled: true
  endpoint: https://hooks.slack.com/services/MYHOOK
  channel: '#verja'
  emoji: ':broken_heart:'
  username: Verja
jira:
  enabled: true
  url: https://jira.example.com/
  user: my_username
  secret: my_secret_password_or_token
  project_key: VERJA
  issue_type: Task
  description_field: Description
  tag_field: Labels
  summary_prefix: Security
  tags: 
    - security
    - verja
    - test
```

I think the configuration explains itself.

### Setup action

Create a file at `.github/workflows/alert.yml`

And add something like this:

```yaml
name: Verja
on: 
  schedule:
    - cron:  '0 3 * * *'
jobs:
  alert:
    runs-on: ubuntu-latest
    steps:
    - uses: actions/checkout@master
    - uses: digitalist-se/verja@master
      env:
        CONFIG: config.yml
        CPE: cpe.txt
    - name: done
      run: echo 'done'
```

This should run the GitHub action at every night at 3 am.

## Open source

This project is open source, under a GNU General Public License.