#!/bin/bash

# =========================
# PHP-FPM Memory Monitor
# Version 1.0
# =========================

CONTAINER_NAME="app"
INTERVAL=10
VERSION="1.0"

# Цвета
RED='\033[0;31m'
YELLOW='\033[0;33m'
GREEN='\033[0;32m'
NC='\033[0m' # No Color

# Разбор аргументов
while [[ $# -gt 0 ]]; do
  case $1 in
    -i|--interval)
      INTERVAL="$2"
      shift 2
      ;;
    -h|--help)
      echo "Usage: $0 [-i interval_seconds] [-h|--help] [-v|--version]"
      exit 0
      ;;
    -v|--version)
      echo "php_fpm_memory.sh version $VERSION"
      exit 0
      ;;
    *)
      echo "Unknown option: $1"
      echo "Usage: $0 [-i interval_seconds] [-h|--help] [-v|--version]"
      exit 1
      ;;
  esac
done

echo "Monitoring PHP-FPM memory in container '$CONTAINER_NAME' every $INTERVAL sec..."
echo

while true; do
  # Получаем статистику воркеров
  WORKER_STATS=$(docker exec -i "$CONTAINER_NAME" sh -c '
    ps --no-headers -o rss,cmd -C php-fpm | awk "
    {
        sum+=\$1;
        if(min==\"\" || \$1<min) min=\$1;
        if(max==\"\" || \$1>max) max=\$1;
        count+=1
    }
    END {
        if(count>0)
            printf(\"%d %.2f %.2f %.2f\", count, sum/1024/count, min/1024, max/1024);
        else
            print \"0 0 0 0\"
    }"
  ')

  read WORKERS AVG MIN MAX <<< $WORKER_STATS

  # Заменяем запятую на точку
  AVG=${AVG/,/.}
  MIN=${MIN/,/.}
  MAX=${MAX/,/.}

  # Суммарная память контейнера
  TOTAL_MEM=$(docker stats "$CONTAINER_NAME" --no-stream --format "{{.MemUsage}}" | awk '{print $1}')

  # Определяем цвет для среднего RSS воркера
  AVG_COLOR=$GREEN
  is_yellow=$(echo "$AVG >= 50 && $AVG < 80" | bc -l 2>/dev/null)
  is_red=$(echo "$AVG >= 80" | bc -l 2>/dev/null)

  if [ "$is_yellow" -eq 1 ]; then
      AVG_COLOR=$YELLOW
  elif [ "$is_red" -eq 1 ]; then
      AVG_COLOR=$RED
  fi

  echo -e "$(date '+%Y-%m-%d %H:%M:%S') | Workers: $WORKERS, Avg: ${AVG_COLOR}${AVG} MB${NC}, Min: ${MIN} MB, Max: ${MAX} MB | Total container memory: $TOTAL_MEM"

  sleep "$INTERVAL"
done
