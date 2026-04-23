# CI

The repository includes two CI configurations:

- GitHub Actions: [`.github/workflows/php.yml`](../.github/workflows/php.yml)
- GitLab CI: [`.gitlab-ci.yml`](../.gitlab-ci.yml)

## Default Checks

- `composer validate --strict`
- `vendor/bin/phpunit`
- `vendor/bin/psalm`

## Why performance tests are separate

Load scenarios are intentionally not part of the default CI path because they are slower and more sensitive to runner variance. They are available through:

- `composer test:performance`
- `composer bench:stream`
