from flask import Flask, request, jsonify
import os

# Фикс для "could not execute a primitive" в PaddlePaddle
os.environ["OMP_NUM_THREADS"] = "1"
os.environ["OPENBLAS_NUM_THREADS"] = "1"
os.environ["MKL_NUM_THREADS"] = "1"

from paddleocr import PaddleOCR
from werkzeug.utils import secure_filename
# Инициализация OCR перенесена внутрь функции для изоляции памяти
app = Flask(__name__)
UPLOAD_FOLDER = '/tmp'
os.makedirs(UPLOAD_FOLDER, exist_ok=True)

@app.route('/ocr', methods=['POST'])
def ocr_image():
    if 'file' not in request.files:
        return jsonify({'error': 'No file uploaded'}), 400

    f = request.files['file']
    filename = secure_filename(f.filename or 'image.jpg')
    fp = os.path.join(UPLOAD_FOLDER, filename)
    f.save(fp)

    try:
        ocr = PaddleOCR(use_angle_cls=True, lang='en', enable_mkldnn=False, use_mkldnn=False, use_gpu=False, cpu_threads=1)
        result = ocr.ocr(fp, cls=True)
        lines = []
        if result and result[0]:
            for box, (text, conf) in [(r[0], r[1]) for r in result[0]]:
                lines.append(text)
        text = "\n".join(lines)
        return jsonify({'text': text})
    finally:
        try:
            os.remove(fp)
        except Exception:
            pass

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=8868, threaded=False)