#!/bin/bash

if [[ ( $@ != *'android'* ) && ( $@ != *'ios'* ) ]]
then
    echo "No platform set"
    echo "-"
    echo "Use: $0 ios --no-final-rm"
    echo "Or: $0 android"
    exit
else
    if [[ $@ != *'--no-rm'* ]]
    then
        rm -rf node_modules/ && rm -rf platforms/ && rm -rf plugins/ && rm package*
        npm cache clean --force
    fi
    if [[ $@ != *'--rma'* ]]
    then

        if [[ $@ != *'--no-platform'* ]]
        then
            echo "Adding platform..."
            if [[ $@ != *'ios'* ]]
            then
                cordova platform add android
            else
                sudo echo "Root permission for Cocoapods granted..."

                echo "Checking Cocoapods dependencies..."
                xcode-select --install
                sudo gem update --system
                sudo gem pristine ffi --version 1.15.5
                sudo brew install rbenv
                sudo rbenv install
                sudo rbenv global
                sudo gem install ffi -- --use-system-libraries
                sudo gem install cocoapods
                gem pristine ffi --version 1.15.5
                gem install cocoapods --user-install

                cordova platform add ios
            fi
        fi

        if [[ $@ != *'--no-plugins'* ]]
        then
            echo "Adding plugins..."
            cordova plugin add cordova-clipboard
            cordova plugin add cordova-plugin-device
            cordova plugin add cordova-plugin-battery-status
            cordova plugin add cordova-plugin-camera
            cordova plugin add cordova-plugin-file
            cordova plugin add cordova-plugin-geolocation
            cordova plugin add cordova-plugin-inappbrowser
            cordova plugin add cordova-plugin-media
            cordova plugin add cordova-plugin-vibration
            cordova plugin add cordova-plugin-x-socialsharing
            cordova plugin add cordova-clarity 

            cordova plugin add cordova-plugin-firebasex@18.0.0 # https://github.com/dpa99c/cordova-plugin-firebasex
        fi

        if [[ $@ != *'ios'* ]]
        then
            if [[ $@ != *'--no-plugins'* ]]
            then
                echo "Extra plugins..."
                cordova plugin add cordova-plugin-zxing # https://github.com/marceloburegio/cordova-plugin-zxing
                # cordova plugin add cordova-plugin-androidx
                # cordova plugin add cordova-plugin-androidx-adapter
            fi
            if [[ $@ != *'--no-apk'* ]]
            then
                echo "Building Android APK..."
                cordova prepare
                cordova build android --prod --release
                if [ -f "platforms/android/app/build/outputs/bundle/release/app-release.aab" ]; then
                    echo "Signing AAB file..."
                    rm app-release.apk
                    java -jar ./tools/bundletool-all-1.14.0.jar build-apks --mode=universal \
                    --bundle="platforms/android/app/build/outputs/bundle/release/app-release.aab" --output="./app-release.apks" \
                    --ks="./keys/app.keystore" --ks-key-alias=PROJECT --ks-pass=pass:PASSWORD
                    mv app-release.apks app-release.zip
                    unzip app-release.zip
                    rm app-release.zip
                    rm toc.pb
                    mv universal.apk app-release.apk
                else
                    echo "Compiled AAB file NOT FOUND"
                fi
            else
                echo "Building Android AAB only..."
                cordova prepare
                cordova build android --release
                if [ -f "platforms/android/app/build/outputs/bundle/release/app-release.aab" ]; then
                    rm app-release.aab
                    jarsigner -verbose -sigalg SHA1withRSA -digestalg SHA1 -storepass "PASSWORD" -keystore "./keys/app.keystore" platforms/android/app/build/outputs/bundle/release/app-release.aab PROJECT
                    mv platforms/android/app/build/outputs/bundle/release/app-release.aab ./
                else
                    echo "Compiled AAB file NOT FOUND"
                fi
            fi
        else
            if [[ $@ != *'--no-plugins'* ]]
            then
                echo "Extra plugins..."
                cordova plugin add cordova-plugin-barcodescanner
                # cordova plugin add cordova-plugin-wkwebview-engine
            fi
            if [[ $@ != *'--no-build'* ]]
            then
                echo "Preparing IOS..."
                cordova clean
                cordova prepare ios

                echo "Preparing Cocoapods..."
                rm -Rf ~/Library/Developer/Xcode/DerivedData/
                cd platforms/ios
                pod repo update
                pod deintegrate
                pod setup
                pod install
                cd ..
                cd ..

                echo "Building IOS..."
                cordova build ios
            fi
        fi
    fi
    if [[ ( $@ != *'--no-rm'* ) && ( $@ != *'--no-final-rm'* ) && ( $@ != *'ios'* ) ]]
    then
        rm -rf node_modules/ && rm -rf platforms/ && rm -rf plugins/ && rm package*
    fi
fi