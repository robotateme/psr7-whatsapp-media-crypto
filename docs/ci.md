# CI

В репозитории есть две CI-конфигурации:

- GitHub Actions: [`.github/workflows/php.yml`](../.github/workflows/php.yml)
- GitLab CI: [`.gitlab-ci.yml`](../.gitlab-ci.yml)

## Базовые проверки

- `composer validate --strict`
- `vendor/bin/phpunit`
- `vendor/bin/psalm`

## Почему тесты производительности вынесены отдельно

Нагрузочные сценарии намеренно не включены в стандартный CI-путь, потому что они медленнее и сильнее зависят от вариативности среды выполнения. Они доступны через:

- `composer test:performance`
- `composer bench:stream`
