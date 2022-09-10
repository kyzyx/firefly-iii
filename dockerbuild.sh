PORT=4049
CONTAINER=firefly-dev

ID=`docker ps -aq --filter "ancestor=$CONTAINER"`
docker stop $ID
docker rm $ID
docker build -t $CONTAINER --platform linux/amd64 --build-arg build_base=apache --build-arg build_platform=8.0 .
docker run -d \
-v /volume1/docker/firefly/storage/upload:/var/www/firefly-iii/storage/upload \
-p $PORT:8080 \
-e APP_KEY=BKXegT7KYqZxgBrDtobceJwIGj5iFuti \
-e DB_HOST=192.168.0.70:3306 \
-e DB_PORT=3306 \
-e DB_CONNECTION=mysql \
-e DB_DATABASE=firefly \
-e DB_USERNAME=root \
-e DB_PASSWORD=h0meserver_DB \
$CONTAINER:latest
