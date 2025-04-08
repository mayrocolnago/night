#!/bin/bash

# Auto deploy wiki

USERPART=""
if [[ -n "$WIKI_DEPLOY_PAT" ]]; then # Project > Settings > Access Tokens
  USERPART="oauth2:${WIKI_DEPLOY_PAT}@" # Project > Settings > CI/CD > Variables > WIKI_DEPLOY_PAT
elif [[ -n "$CI_JOB_TOKEN" ]]; then
  USERPART="gitlab-ci-token:${CI_JOB_TOKEN}@"
fi

FULL_REPO_URL=$(git config --get remote.origin.url)
REPO_URL=$(echo "$FULL_REPO_URL" | sed -E 's|https://[^/]+@|https://|')

WIKI_URL="${REPO_URL%.git}.wiki.git"

FULL_WIKI_URL="${WIKI_URL/https:\/\//https://${USERPART}}"

cd wikis
git config --global --add safe.directory "$(pwd)"

git clone "$FULL_WIKI_URL" wikigit
if [[ -d "wikigit/.git" ]]; then
    mv wikigit/.git ./
    rm -rf wikigit
    git config user.name "GitLab"
    git config user.email "gitlab@mg.gitlab.com"
    git add .
    git commit -m "$(git status | grep ': ')"
    git push
    rm -rf .git
else
    echo "Cloning failed"
fi
cd ..