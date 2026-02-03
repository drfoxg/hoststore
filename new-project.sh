#!/usr/bin/env bash
#set -e

# =========================
# Colors
# =========================
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# =========================
# Paths
# =========================
ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
ENV_DIR="$ROOT_DIR/docker/env"

# =========================
# Args
# =========================
COMMAND=$1
TARGET=$2          # local | testing | production
PROJECT=$3
DB_TYPE=$4         # mysql | pgsql
DB_MODE=$5         # shared | local

# =========================
# Usage
# =========================
usage() {
  echo
  echo "Usage:"
  echo "  $0 env  [local|testing|production] PROJECT DB_TYPE[mysql|pgsql] DB_MODE[shared|local]"
  echo "  $0 init PROJECT LARAVEL_VERSION DB_TYPE[mysql|pgsql] DB_MODE[shared|local]"
  echo
  exit 1
}

require_env_secret() {
  if [[ -z "$ENV_SECRET" ]]; then
    echo -e "${RED}ENV_SECRET is not set${NC}"
    exit 1
  fi
}

# =========================
# ENV generation
# =========================
build_env() {
  [[ -z "$TARGET" || -z "$PROJECT" ]] && usage
  require_env_secret

  BASE_TEMPLATE="$ENV_DIR/.env.template.base"
  BASE_OUTPUT="$ENV_DIR/.env.base"
  BASE_ENC="$ENV_DIR/.env.base.enc"

  OVERLAY_TEMPLATE="$ENV_DIR/.env.template.${TARGET}.overlay"
  OVERLAY_OUTPUT="$ENV_DIR/.env.${TARGET}.overlay"

  TARGET_ENV="$ROOT_DIR/.env"

  [[ ! -f "$BASE_TEMPLATE" ]] && {
    echo -e "${RED}.env.template.base not found${NC}"
    exit 1
  }

  # -------------------------
  # Base env
  # -------------------------
  cp "$BASE_TEMPLATE" "$BASE_OUTPUT"
  echo -e "${YELLOW}.env.base created from template${NC}"

  sed -i "s|{{PROJECT}}|${PROJECT}|g" "$BASE_OUTPUT"
  sed -i "s|APP_NAME=.*|APP_NAME=${PROJECT}|g" "$BASE_OUTPUT"
  sed -i "s|APP_URL=.*|APP_URL=http://${PROJECT}.sulfurfun.ru|g" "$BASE_OUTPUT"

  # -------------------------
  # DB logic
  # -------------------------
  if [[ -n "$DB_TYPE" && -n "$DB_MODE" ]]; then
    case "$DB_TYPE" in
      mysql)
        sed -i "s|DB_CONNECTION=.*|DB_CONNECTION=mysql|g" "$BASE_OUTPUT"
        sed -i "s|DB_PORT=.*|DB_PORT=3306|g" "$BASE_OUTPUT"
        [[ "$DB_MODE" == "shared" ]] && \
          sed -i "s|DB_HOST=.*|DB_HOST=mysql-dev|g" "$BASE_OUTPUT"
        ;;
      pgsql)
        sed -i "s|DB_CONNECTION=.*|DB_CONNECTION=pgsql|g" "$BASE_OUTPUT"
        sed -i "s|DB_PORT=.*|DB_PORT=5432|g" "$BASE_OUTPUT"
        [[ "$DB_MODE" == "shared" ]] && \
          sed -i "s|DB_HOST=.*|DB_HOST=pgsql-dev|g" "$BASE_OUTPUT"
        ;;
      *)
        echo -e "${RED}Unsupported DB_TYPE${NC}"
        exit 1
        ;;
    esac

    sed -i "s|DB_TYPE=.*|DB_TYPE=$DB_TYPE|g" "$BASE_OUTPUT"
    sed -i "s|DB_MODE=.*|DB_MODE=$DB_MODE|g" "$BASE_OUTPUT"
  fi

  # -------------------------
  # Overlay generation
  # -------------------------
  if [[ -f "$OVERLAY_TEMPLATE" ]]; then
    cp "$OVERLAY_TEMPLATE" "$OVERLAY_OUTPUT"
    sed -i "s|{{PROJECT}}|${PROJECT}|g" "$OVERLAY_OUTPUT"
    echo -e "${YELLOW}Overlay created: $(basename "$OVERLAY_OUTPUT")${NC}"
  fi

  # -------------------------
  # Final .env
  # -------------------------
  if [[ -f "$OVERLAY_OUTPUT" ]]; then
    cat "$BASE_OUTPUT" "$OVERLAY_OUTPUT" > "$TARGET_ENV"
  else
    cp "$BASE_OUTPUT" "$TARGET_ENV"
  fi

  echo -e "${GREEN}Generated $TARGET_ENV${NC}"

  # -------------------------
  # Encrypt base
  # -------------------------
  openssl enc -aes-256-cbc -pbkdf2 \
    -in "$BASE_OUTPUT" \
    -out "$BASE_ENC" \
    -pass env:ENV_SECRET

  echo -e "${GREEN}.env.base encrypted to .env.base.enc${NC}"
}

# =========================
# Project init
# =========================
init_project() {
  echo -e "Подставляем переменные"
  PROJECT=$2
  LARAVEL=$3
  DB_TYPE=$4
  DB_MODE=$5

  [[ -z "$PROJECT" || -z "$LARAVEL" || -z "$DB_TYPE" || -z "$DB_MODE" ]] && usage

  [[ ! -f "$ROOT_DIR/.env" ]] && {
    echo -e "${RED}.env not found. Run env command first.${NC}"
    exit 1
  }

  # -------------------------
  # PHP image & version (определяем ДО создания Laravel)
  # -------------------------
  case "$LARAVEL" in
    10) PHP_FPM_IMAGE="php:8.2-fpm" ;;
    11) PHP_FPM_IMAGE="php:8.3-fpm" ;;
    12) PHP_FPM_IMAGE="php:8.4-fpm" ;;
    *)
      echo -e "${RED}Unsupported Laravel version${NC}"
      exit 1
      ;;
  esac

  # Извлекаем версию PHP из образа (php:8.3-fpm → 8.3)
  PHP_VERSION=$(echo "$PHP_FPM_IMAGE" | grep -oP '(?<=php:)\d+\.\d+')
  
  # Проверяем что Dockerfile существует
  DOCKERFILE_PATH="docker/php/Dockerfile.${PHP_VERSION}"
  if [[ ! -f "$ROOT_DIR/$DOCKERFILE_PATH" ]]; then
    echo -e "${RED}Dockerfile not found: $DOCKERFILE_PATH${NC}"
    echo -e "${YELLOW}Available Dockerfiles:${NC}"
    ls -1 "$ROOT_DIR/docker/php/Dockerfile."* 2>/dev/null || echo "  None found"
    exit 1
  fi
  
  echo -e "${GREEN}Using PHP $PHP_VERSION (Dockerfile: $DOCKERFILE_PATH)${NC}"

  # -------------------------
  # Laravel (используем правильную версию PHP)
  # -------------------------
  if [[ ! -f artisan ]]; then
    echo -e "${YELLOW}Creating Laravel project with PHP ${PHP_VERSION}${NC}"
    
    TMP_DIR=".laravel_tmp"
    if [[ -d "$TMP_DIR" ]]; then
      echo -e "\033[31mERROR:\033[0m $TMP_DIR already exists"
      exit 1
    fi

    # Сначала собираем PHP образ с нужными расширениями
    echo -e "${YELLOW}Building PHP image...${NC}"
    docker build -t ${PROJECT}-php:${PHP_VERSION} -f "$ROOT_DIR/$DOCKERFILE_PATH" "$ROOT_DIR"

    # Используем собранный образ для создания Laravel
    echo -e "${YELLOW}Creating Laravel ${LARAVEL} project with PHP ${PHP_VERSION}...${NC}"

    # Create Laravel into temp dir
    docker run --rm \
      -u "$(id -u):$(id -g)" \
      -v "$(pwd):/app" \
      -w /app \
      ${PROJECT}-php:${PHP_VERSION} \
      composer create-project laravel/laravel:"^$LARAVEL" "$TMP_DIR" --prefer-dist --no-interaction

    # whitelist move
    ITEMS=(
      app
      bootstrap
      config
      database
      public
      resources
      routes
      storage
      tests
      vendor
      artisan
      composer.json
      composer.lock
      phpunit.xml
    )

    for ITEM in "${ITEMS[@]}"; do
      if [[ -e "$TMP_DIR/$ITEM" ]]; then
        mv "$TMP_DIR/$ITEM" .
      fi
    done

    rm -rf "$TMP_DIR"

    echo -e "\033[32mLaravel files installed safely\033[0m"
  fi

  # -------------------------
  # DB images
  # -------------------------
  if [[ "$DB_TYPE" == "mysql" ]]; then
    DB_IMAGE="mysql:8.4.8"
    DB_STORAGE_PATH="mysql"
  elif [[ "$DB_TYPE" == "pgsql" ]]; then
    DB_IMAGE="postgres:16"
    DB_STORAGE_PATH="postgresql/data"
  else
    echo -e "${RED}Unsupported DB_TYPE${NC}"
    exit 1
  fi

  [[ "$DB_MODE" == "local" ]] && DB_VOLUME="${DB_TYPE}-${PROJECT}"

  # -------------------------
  # docker-compose
  # -------------------------
  cp docker/compose/docker-compose.template.yml docker-compose.yml  

  # Подставляем обычные переменные
  sed -i "s|{{PROJECT}}|$PROJECT|g" docker-compose.yml
  sed -i "s|{{PHP_FPM_IMAGE}}|$PHP_FPM_IMAGE|g" docker-compose.yml
  sed -i "s|{{PHP_VERSION}}|$PHP_VERSION|g" docker-compose.yml
  sed -i "s|{{DB_IMAGE}}|$DB_IMAGE|g" docker-compose.yml
  sed -i "s|{{DB_STORAGE_PATH}}|$DB_STORAGE_PATH|g" docker-compose.yml
  sed -i "s|{{DB_VOLUME}}|$DB_VOLUME|g" docker-compose.yml

  # Функция обработки условий if/else/endif с корректным отслеживанием вложенности
  process_template() {
    local file="$1"
    local var_name="$2"
    local var_value="$3"
    local tmpfile="${file}.tmp"
    
    # Массив для отслеживания состояний на каждом уровне вложенности
    # state: 0 = не наша переменная, 1 = наша переменная условие true, 2 = наша переменная условие false
    local -a states=()
    local level=0
    local skip=0
    
    while IFS= read -r line || [[ -n "$line" ]]; do
      # Любой {% if ... %}
      if [[ "$line" =~ \{%[[:space:]]*if[[:space:]] ]]; then
        ((level++))
        
        # Проверяем, это наша переменная?
        if [[ "$line" =~ \{%[[:space:]]*if[[:space:]]+${var_name}[[:space:]]*==[[:space:]]*\"([^\"]+)\"[[:space:]]*%\} ]]; then
          local cond_val="${BASH_REMATCH[1]}"
          if [[ "$cond_val" == "$var_value" ]]; then
            states[$level]=1  # условие истинно
          else
            states[$level]=2  # условие ложно, пропускаем
            ((skip++))
          fi
        else
          # Не наша переменная - печатаем как есть
          states[$level]=0
          if [[ $skip -eq 0 ]]; then
            echo "$line"
          fi
        fi
        continue
      fi
      
      # {% else %}
      if [[ "$line" =~ \{%[[:space:]]*else[[:space:]]*%\} ]]; then
        if [[ ${states[$level]} -eq 1 ]]; then
          # Были в true-ветке, теперь skip
          states[$level]=2
          ((skip++))
        elif [[ ${states[$level]} -eq 2 ]]; then
          # Были в false-ветке, теперь печатаем
          states[$level]=1
          ((skip--))
        else
          # Не наша переменная
          if [[ $skip -eq 0 ]]; then
            echo "$line"
          fi
        fi
        continue
      fi
      
      # {% endif %}
      if [[ "$line" =~ \{%[[:space:]]*endif[[:space:]]*%\} ]]; then
        if [[ ${states[$level]} -eq 2 ]]; then
          ((skip--))
        elif [[ ${states[$level]} -eq 0 ]]; then
          if [[ $skip -eq 0 ]]; then
            echo "$line"
          fi
        fi
        unset "states[$level]"
        ((level--))
        continue
      fi
      
      # Обычная строка
      if [[ $skip -eq 0 ]]; then
        echo "$line"
      fi
    done < "$file" > "$tmpfile"
    
    mv "$tmpfile" "$file"
  }
  
  # Обрабатываем сначала внутренние условия DB_TYPE, потом внешние DB_MODE
  process_template docker-compose.yml "DB_TYPE" "$DB_TYPE"
  process_template docker-compose.yml "DB_MODE" "$DB_MODE"
  
  # Удаляем пустые depends_on (без элементов)
  awk '
    /^[[:space:]]*depends_on:[[:space:]]*$/ {
      prev = $0
      if (getline > 0) {
        if ($0 ~ /^[[:space:]]*-/) {
          print prev
          print
        } else {
          print
        }
        next
      }
    }
    { print }
  ' docker-compose.yml > docker-compose.yml.tmp && mv docker-compose.yml.tmp docker-compose.yml


  # -------------------------
  # nginx
  # -------------------------
  TEMPLATE="$ROOT_DIR/docker/nginx/templates/app.template.conf"
  TARGET="$ROOT_DIR/docker/nginx/conf.d/app.conf"

  # Создаём директорию, если её нет
  #mkdir -p "$(dirname "$TARGET")"

  # Копируем шаблон
  cp "$TEMPLATE" "$TARGET"

  # Подставляем переменные
  sed -i "s|{{PROJECT}}|$PROJECT|g" "$TARGET"

  echo -e "${GREEN}Starting Docker containers${NC}"
  docker compose up -d --build
}

# =========================
# Main
# =========================
case "$COMMAND" in
  env)  build_env;;
  init) init_project "$@";;
  *)    usage;;
esac