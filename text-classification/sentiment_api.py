from flask import Flask, request, jsonify
import joblib
import re
import nltk
from nltk.corpus import stopwords
from nltk.tokenize import word_tokenize
import numpy as np
import sys

# Add the directory containing the stop words file to sys.path
# Replace with the actual path to your stop words file
# sys.path.append('/content/drive/MyDrive/Datasets/')

# Make sure you have the necessary NLTK data downloaded (run this in your Colab notebook if you haven't)
# nltk.download('punkt', quiet=True)
# nltk.download('stopwords', quiet=True)
# nltk.download('punkt_tab', quiet=True) # Ensure punkt_tab is downloaded if needed for Tagalog


# Function to load stop words from a file
def load_stopwords(filepath):
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            stopwords = set(line.strip() for line in f if line.strip())
        return stopwords
    except FileNotFoundError:
        print(f"Error: Stop words file not found at {filepath}. Using an empty set of stop words.")
        return set()

tagalog_stop_words_file = 'tagalog_stopwords.txt'
tagalog_stop_words = load_stopwords(tagalog_stop_words_file)


def preprocess_text(text):
    """
    Cleans and preprocesses a single text string using Tagalog stop words.
    """
    # Ensure text is a string, handle potential NaN values
    if not isinstance(text, str):
        return ""
    # Remove HTML tags
    text = re.sub('<[^>]*>', '', text)
    # Remove special characters and numbers, and convert to lowercase
    text = re.sub('[^a-zA-Z]', ' ', text).lower()
    # Tokenize the text
    tokens = word_tokenize(text)
    # Remove stopwords (using loaded Tagalog stop words)
    filtered_tokens = [word for word in tokens if word not in tagalog_stop_words]
    # Join the tokens back into a string
    return ' '.join(filtered_tokens)


app = Flask(__name__)

# Load the trained model and vectorizer
try:
    trained_model = joblib.load('sentiment_model.joblib')
    trained_vectorizer = joblib.load('tfidf_vectorizer.joblib')
except FileNotFoundError:
    print("Error: Model or vectorizer file not found. Make sure 'sentiment_model.joblib' and 'tfidf_vectorizer.joblib' are in the same directory.")
    sys.exit(1)

@app.route('/predict_sentiment', methods=['POST'])
def predict():
    """
    API endpoint to receive text and return sentiment prediction.
    Expects JSON data with a 'text' key.
    """
    data = request.get_json()
    if 'text' not in data:
        return jsonify({'error': 'No "text" key found in the request data'}), 400

    input_text = data['text']

    # Preprocess the input text
    clean_input_text = preprocess_text(input_text)

    # Vectorize the preprocessed text
    input_text_vec = trained_vectorizer.transform([clean_input_text])

    # Make a prediction and get probabilities
    prediction = trained_model.predict(input_text_vec)
    probabilities = trained_model.predict_proba(input_text_vec)[0]

    # Get the predicted class label and its probability
    predicted_class = prediction[0]
    class_labels = trained_model.classes_
    predicted_proba = probabilities[np.where(class_labels == predicted_class)][0]


    return jsonify({
        'sentiment': predicted_class,
        'confidence': float(predicted_proba) # Convert to float for JSON serialization
    })

if __name__ == '__main__':
    # To run this in a local environment for testing, you can use:
    app.run(debug=True, port=5000)

    # For deployment, you might use a production-ready server like gunicorn or uWSGI
    # In Colab, you can use ngrok to expose the local server for testing
    # from flask_ngrok import run_with_ngrok
    # run_with_ngrok(app)
    # app.run()
    print("Flask app is set up. To run it locally for testing, uncomment the appropriate lines in the __main__ block.")
    print("For Colab, you can use ngrok to expose the server.")
    # Note: Running a Flask app directly in Colab's environment for production use
    # is not recommended. Consider deployment options like Google Cloud Functions,
    # App Engine, or Cloud Run for a production API.
