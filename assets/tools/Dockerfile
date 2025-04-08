FROM ubuntu:18.04

# Updating repositories and tools...
RUN apt-get update -y && \
    apt-get install -y wget curl

# Installing NodeJS 12.x...
RUN apt-get remove -y nodejs && \
    curl -sL https://deb.nodesource.com/setup_12.x | bash - && \
    apt-get install -y nodejs

# Installing Java JDK8 Headless...
RUN apt-get install -y openjdk-8-jdk-headless build-essential libssl-dev zip zipalign

# Installing Android SDK tools...
RUN wget https://dl.google.com/android/repository/sdk-tools-linux-4333796.zip && \
    mkdir -p /usr/lib/android-sdk && \
    unzip sdk-tools-linux-4333796.zip -d /usr/lib/android-sdk && \
    rm sdk-tools-linux-4333796.zip

# Installing Apache ANT...
RUN wget https://dlcdn.apache.org//ant/binaries/apache-ant-1.9.16-bin.zip && \
    mkdir -p /usr/lib/ant && \
    unzip apache-ant-1.9.16-bin.zip -d /usr/lib/ant && \
    rm apache-ant-1.9.16-bin.zip

# Installing Gradle...
RUN wget https://services.gradle.org/distributions/gradle-5.0-bin.zip && \
    mkdir -p /opt/gradle && \
    unzip -d /opt/gradle gradle-5.0-bin.zip && \
    rm gradle-5.0-bin.zip

# Updating NPM and Installing Cordova...
RUN npm -g install npm  --no-audit && \
    npm -g install cordova  --no-audit && \
    npm -g install gulp --no-audit 

# Clearing remaining files...
RUN apt-get autoremove -y

# Setting environment variables...
ENV ANDROID_HOME="/usr/lib/android-sdk"
ENV ANDROID_SDK_ROOT="/usr/lib/android-sdk"
ENV ANT_HOME="/usr/lib/ant"
ENV PATH="/tools:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/opt/gradle/gradle-5.0/bin:/usr/lib/android-sdk/tools:/usr/lib/android-sdk/tools/bin:/usr/lib/android-sdk/platform-tools"
ENV CORDOVA_ANDROID_GRADLE_DISTRIBUTION_URL="https\\://services.gradle.org/distributions/gradle-5.0-bin.zip"

# Configuring SDKManager...
RUN yes | sdkmanager --update && \
    yes | sdkmanager "platforms;android-29" "build-tools;28.0.3" "extras;google;google_play_services" && \
    yes | sdkmanager --licenses

RUN cordova telemetry off

WORKDIR /workspace