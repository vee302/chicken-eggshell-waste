import sys
import os
import json

def main():
    image_path = sys.argv[1]
    surface_type = sys.argv[2] if len(sys.argv) > 2 else "unknown"

    # Initialize default error response
    result = {
        "success": False,
        "surface_type": surface_type,
        "ridge_clarity_score": 0.0,
        "visibility_score": 0.0,
        "adhesion_score": 0.0,
        "contrast_score": 0.0,
        "accuracy_score": 0.0,
        "quality_label": "Needs Improvement",
        "message": "Unable to evaluate fingerprint image."
    }

    # Check if the image file exists on disk
    if not os.path.isfile(image_path):
        result["message"] = f"Image file not found: {image_path}"
        print(json.dumps(result))
        sys.exit(1)

    try:
        # Import cv2 and numpy internally to catch import errors cleanly
        import cv2
        import numpy as np
    except ImportError:
        result["message"] = "Required Python packages (opencv-python, numpy) are not installed."
        print(json.dumps(result))
        sys.exit(1)

    try:
        # Read the image using OpenCV
        img = cv2.imread(image_path)
        if img is None:
            result["message"] = "Failed to load image. The file may be corrupt or not a valid image format."
            print(json.dumps(result))
            sys.exit(1)

        # Convert image to grayscale for intensity and pattern analysis
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)

        # 1. Evaluate Contrast
        # Measures the difference between fingerprint ridges and background
        std_dev = float(np.std(gray))
        p5, p95 = np.percentile(gray, [5, 95])
        diff = float(p95 - p5)
        # Combine intensity standard deviation and 95th-5th range
        contrast_score = (std_dev / 60.0) * 50.0 + (diff / 180.0) * 50.0
        contrast_score = max(0.0, min(100.0, contrast_score))

        # Enhance contrast using CLAHE for ridge metrics
        clahe = cv2.createCLAHE(clipLimit=3.0, tileGridSize=(8, 8))
        enhanced = clahe.apply(gray)
        blurred = cv2.GaussianBlur(enhanced, (5, 5), 0)

        # 2. Evaluate Ridge Clarity
        # Measures ridge sharpness using Laplacian variance
        laplacian_var = cv2.Laplacian(blurred, cv2.CV_64F).var()
        if laplacian_var < 80:
            ridge_clarity_score = (laplacian_var / 80.0) * 55.0
        elif laplacian_var < 350:
            ridge_clarity_score = 55.0 + ((laplacian_var - 80.0) / (350.0 - 80.0)) * 24.0
        else:
            ridge_clarity_score = 79.0 + min(21.0, ((laplacian_var - 350.0) / 800.0) * 21.0)
        ridge_clarity_score = max(0.0, min(100.0, ridge_clarity_score))

        # 3. Evaluate Visibility
        # Measures edge density to estimate visibility of the print pattern against background
        edges = cv2.Canny(blurred, 50, 150)
        edge_density = float(np.sum(edges > 0)) / float(edges.size)
        if edge_density < 0.08:
            visibility_score = (edge_density / 0.08) * 85.0
        else:
            visibility_score = 85.0 + min(15.0, ((edge_density - 0.08) / 0.07) * 15.0)
        visibility_score = max(0.0, min(100.0, visibility_score))

        # 4. Evaluate Adhesion Quality
        # Measures powder coverage using Otsu binary thresholding
        _, thresh = cv2.threshold(blurred, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU)
        powder_coverage = float(np.sum(thresh == 255)) / float(thresh.size)
        if powder_coverage < 0.1:
            adhesion_score = (powder_coverage / 0.1) * 40.0
        elif powder_coverage < 0.35:
            adhesion_score = 40.0 + ((powder_coverage - 0.1) / 0.25) * 45.0
        elif powder_coverage <= 0.60:
            adhesion_score = 85.0 + ((powder_coverage - 0.35) / 0.25) * 15.0
        else:
            # Over-powdering or blob lift reduces adhesion quality score
            adhesion_score = max(0.0, 100.0 - ((powder_coverage - 0.60) / 0.40) * 60.0)
        adhesion_score = max(0.0, min(100.0, adhesion_score))

        # 5. Composite Score
        # Average of clarity, visibility, adhesion, and contrast scores
        accuracy_score = (ridge_clarity_score + visibility_score + adhesion_score + contrast_score) / 4.0

        # Interpretation labels
        if accuracy_score >= 90.0:
            quality_label = "Excellent"
        elif accuracy_score >= 80.0:
            quality_label = "Very Good"
        elif accuracy_score >= 70.0:
            quality_label = "Good"
        elif accuracy_score >= 60.0:
            quality_label = "Fair"
        else:
            quality_label = "Needs Improvement"

        # Round values for display consistency
        result = {
            "success": True,
            "surface_type": surface_type,
            "ridge_clarity_score": round(ridge_clarity_score, 2),
            "visibility_score": round(visibility_score, 2),
            "adhesion_score": round(adhesion_score, 2),
            "contrast_score": round(contrast_score, 2),
            "accuracy_score": round(accuracy_score, 2),
            "quality_label": quality_label,
            "message": "Automated image evaluation processed successfully."
        }

    except Exception as e:
        result["message"] = f"Evaluation failed: {str(e)}"

    print(json.dumps(result))

if __name__ == "__main__":
    main()
