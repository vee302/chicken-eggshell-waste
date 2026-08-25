/**
 * assets/js/firebase_config.js - Firebase Authentication & Firebase App Handler
 */

(function () {
    // Read Firebase config values from global window object injected by PHP
    const config = window.FIREBASE_CONFIG || {};

    if (!config.apiKey || config.apiKey === "") {
        console.warn("Firebase Auth: Config credentials not fully populated in .env file.");
    }

    // Initialize Firebase if Firebase SDK is loaded
    if (typeof firebase !== "undefined" && config.apiKey) {
        if (!firebase.apps.length) {
            firebase.initializeApp({
                apiKey: config.apiKey,
                authDomain: config.authDomain,
                projectId: config.projectId,
                storageBucket: config.storageBucket,
                messagingSenderId: config.messagingSenderId,
                appId: config.appId
            });
            console.log("Firebase App & Authentication initialized successfully.");
        }
    }

    /**
     * Send Password Reset Email via Firebase Auth
     * @param {string} email
     * @returns {Promise<{success: boolean, message: string}>}
     */
    window.sendFirebasePasswordReset = async function (email) {
        if (typeof firebase === "undefined" || !firebase.auth) {
            return { success: false, message: "Firebase Auth SDK is not loaded." };
        }
        try {
            await firebase.auth().sendPasswordResetEmail(email);
            return { success: true, message: "Firebase Password Reset email sent successfully to " + email };
        } catch (error) {
            console.error("Firebase Password Reset Error:", error);
            return { success: false, message: error.message };
        }
    };
})();
