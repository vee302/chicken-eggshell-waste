import sys
import os
import json

def main():
    # Initialize default error response
    result = {
        "success": False,
        "clarity_score": 0,
        "quality_result": "Invalid Image",
        "message": "An unknown error occurred during image processing."
    }

    # 1. Check if the image path argument was provided
    if len(sys.argv) < 2:
        result["message"] = "No image path argument provided."
        print(json.dumps(result))
        sys.exit(1)

    image_path = sys.argv[1]

    # 2. Check if the image file exists on the disk
    if not os.path.isfile(image_path):
        result["message"] = f"Image file not found: {image_path}"
        print(json.dumps(result))
        sys.exit(1)

    try:
        # Import cv2 and numpy internally to catch import errors cleanly in JSON format
        import cv2
        import numpy as np
    except ImportError:
        result["message"] = "Required Python packages (opencv-python, numpy) are not installed."
        print(json.dumps(result))
        sys.exit(1)

    try:
        # 3. Read the image using OpenCV
        img = cv2.imread(image_path)
        if img is None:
            result["message"] = "Failed to load image. The file may be corrupt or not a valid image format."
            print(json.dumps(result))
            sys.exit(1)

        # 4. Convert the image to grayscale
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)

        # 5. Enhance the contrast of the image using CLAHE (Contrast Limited Adaptive Histogram Equalization)
        clahe = cv2.createCLAHE(clipLimit=2.5, tileGridSize=(8, 8))
        enhanced = clahe.apply(gray)

        # 6. Reduce image noise using a gentle Gaussian Blur to preserve fingerprint ridges
        blurred = cv2.GaussianBlur(enhanced, (5, 5), 0)

        # 7. Analyze basic image clarity using the Laplacian Variance method (standard sharpness metric)
        # Fingerprints with clear, high-contrast ridges produce high Laplacian variance.
        laplacian_var = cv2.Laplacian(blurred, cv2.CV_64F).var()

        # 8. Generate a normalized clarity score (0 to 100) using a smooth mapping heuristic
        # Heuristics based on typical fingerprint contrast and ridge sharpness:
        if laplacian_var < 80:
            # Low contrast or very blurry image
            clarity_score = int((laplacian_var / 80.0) * 55.0)
        elif laplacian_var < 350:
            # Moderate clarity/ridge visibility
            clarity_score = int(55.0 + ((laplacian_var - 80.0) / (350.0 - 80.0)) * 24.0)
        else:
            # High clarity/sharp visible ridges
            clarity_score = int(79.0 + min(21.0, ((laplacian_var - 350.0) / 800.0) * 21.0))

        # Ensure score is strictly within the 0 to 100 range
        clarity_score = max(0, min(100, clarity_score))

        # 9. Generate a quality rating based on the computed score
        if clarity_score >= 80:
            quality_result = "High Quality"
        elif clarity_score >= 60:
            quality_result = "Moderate Quality"
        else:
            quality_result = "Low Quality"

        # Construct success response
        result = {
            "success": True,
            "clarity_score": clarity_score,
            "quality_result": quality_result,
            "message": "Image processed successfully."
        }

    except Exception as e:
        result["message"] = f"Image processing failed: {str(e)}"

    # 10. Return the output as JSON
    print(json.dumps(result))

if __name__ == "__main__":
    main()
