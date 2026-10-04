FROM cnbcool/default-dev-env:latest

USER root
ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update -qq \
 && apt-get install -y -qq \
      php-cli php-mysql php-gd php-curl php-xml php-mbstring php-zip \
      mariadb-server \
 && rm -rf /var/lib/apt/lists/*
