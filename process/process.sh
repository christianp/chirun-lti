#!/bin/bash
error() { echo "An error has occured launching the CourseBuilder LTI document processor. Please contact your local administration." 1>&2; exit 1; }
failed() { echo "Your document failed to build with CourseBuilder. If the error shown above is TeX related, try simplifying your document by removing unsupported LaTeX packages and try again." 1>&2; exit 1; }

while getopts ":g:d:b:" o
do
  case "${o}" in
    g) guid=${OPTARG} ;;
    d) doc=${OPTARG} ;;
    b) base=${OPTARG} ;;
    *) usage ;;
  esac
done
shift $((OPTIND-1))
[[ -n "${guid}" && -n "${base}" ]] || error

UPLOAD_TARGET="$(pwd)/../upload/${guid//\//_}"
PROCESS_TARGET="$(pwd)/../process/${guid//\//_}"
CONTENT_TARGET="$(pwd)/../content/${guid//\//_}"
[[ -d "${UPLOAD_TARGET}" ]] || error
UPLOAD_TARGET="$(realpath -s $UPLOAD_TARGET)/"
PROCESS_TARGET="$(realpath -s $PROCESS_TARGET)/"
CONTENT_TARGET="$(realpath -s $CONTENT_TARGET)/"

rsync -a "${UPLOAD_TARGET}" "${PROCESS_TARGET}"

cp "templates/Makefile" "${PROCESS_TARGET}"
if [[ ! -f "${PROCESS_TARGET}/config.yml" ]]; then
  cp "templates/config.yml" "${PROCESS_TARGET}"
  sed -i -e "s+{BASE}+${base}+g" -e "s/{GUID}/${guid}/g" -e "s/{DOCUMENT}/${doc}/g" "${PROCESS_TARGET}config.yml"
else
  cp "${PROCESS_TARGET}config.yml" "${PROCESS_TARGET}config.yml.orig"
  echo "base_dir: ${base}" >> "${PROCESS_TARGET}config.yml"
  echo "code: ${guid}" >> "${PROCESS_TARGET}config.yml"
  echo "root_url: '{base}/{code}/{theme}/'" >> "${PROCESS_TARGET}config.yml"
fi

cd "${PROCESS_TARGET}"
docker run --rm -v "$(pwd):/opt/cb/" -w /opt/cb coursebuilder/coursebuilder-docker make
[[ $? -eq 0 ]] || failed

echo "Build successful. Copying output to LTI content directory..."
rsync -a "build/" ${CONTENT_TARGET}
cp config.yml ${CONTENT_TARGET}
chown -R programs:www-data ${CONTENT_TARGET}
chmod -R g+w ${CONTENT_TARGET}

# Tidy up processing directory
docker run --rm -v "$(pwd):/opt/cb/" -w /opt/cb coursebuilder/coursebuilder-docker make clean
[[ $? -eq 0 ]] || failed
rm -rf ${PROCESS_TARGET}

echo "Finished!"
