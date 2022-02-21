ARG build_platform
ARG build_base
FROM fireflyiii/base:$build_base-$build_platform

# For more information about fireflyiii/base visit https://dev.azure.com/firefly-iii/BaseImage

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
COPY entrypoint-fpm.sh /usr/local/bin/entrypoint-fpm.sh
COPY counter.txt /var/www/counter-main.txt
COPY date.txt /var/www/build-date-main.txt

RUN apt-get -y update
RUN apt-get -y install git

#ARG version
#ENV VERSION=$version
#### Get official Firefly-iii release
#RUN curl -SL https://github.com/firefly-iii/firefly-iii/archive/$VERSION.tar.gz | tar xzC $FIREFLY_III_PATH --strip-components 1 && \
#### Run from current source
#COPY ./ $FIREFLY_III_PATH
#### Run from particular branch
RUN git clone --no-checkout https://github.com/kyzyx/firefly-iii.git $FIREFLY_III_PATH/tmp
RUN mv $FIREFLY_III_PATH/tmp/.git $FIREFLY_III_PATH && rm -rf $FIREFLY_III_PATH/tmp
RUN git reset --hard HEAD

RUN chmod -R 775 $FIREFLY_III_PATH && \
    composer install --prefer-dist --no-dev --no-scripts && /usr/local/bin/finalize-image.sh

COPY alerts.json /var/www/html/resources/alerts.json

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
