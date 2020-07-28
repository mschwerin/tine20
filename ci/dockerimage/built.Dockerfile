# description:
#   The final tine20 image. It should work! And contain all important stuff.
#   This image is build in to steps. First the build process is run in the build image. And then result is copyed into a
#   clean base image.
#
# build:
#   $ docker build [...] --build-arg='BASE_IMAGE=base-tag' --build-arg='SOURCE_IMAGE=source-tag' .
#
# ARGS:
#   BASE_IMAGE=base
#   BUILD_IMAGE=build
#   TINE20ROOT=/usr/share
#   todo comment vars
#   RELEASE=local -
#   CODENAME=local -
#   REVISION=local -

ARG BASE_IMAGE=base
ARG BUILD_IMAGE=build

#  -    -    -    -    -    -    -    -    -    -    -    -    -    -    -    -    -    -    -    -    -    -    -    -
FROM ${BUILD_IMAGE} as build-copy
# COPY --from can not use build args

FROM ${BASE_IMAGE} as built
ARG TINE20ROOT=/usr/share
COPY --from=build-copy ${TINE20ROOT}/tine20 ${TINE20ROOT}/tine20