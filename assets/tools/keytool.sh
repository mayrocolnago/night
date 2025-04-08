#!/bin/bash

if [[ ( $@ != *'--new-keystore'* ) ]]
then
    echo "..."
else
    keytool -genkey -dname "CN=Project, OU=Project, O=Project, L=Sao Paulo, S=SP, C=BR" -v -keystore "../app.keystore" -alias "project" -storepass "password" -keyalg RSA -keysize 2048 -validity 10000
fi

if [[ ( $@ != *'--upload-key'* ) ]]
then
    echo "..."
else
    java -jar pepk.jar --keystore=../keys/app.keystore --alias=project --output=../keys/android_upload_key.zip --include-cert --rsa-aes-encryption --encryption-key-path=../keys/android_public_key.pem
fi